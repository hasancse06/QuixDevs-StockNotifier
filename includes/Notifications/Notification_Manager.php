<?php
/**
 * Notification Manager component.
 *
 * @package QuixDevsStockNotifier
 */

namespace QuixDevs\StockNotifier\Notifications;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use QuixDevs\StockNotifier\Data\Notification_Repository;
use QuixDevs\StockNotifier\Recipients\{Recipient, Recipient_Resolver};
use QuixDevs\StockNotifier\Support\{Settings, Lock_Manager, Logger};

/** Resolve asynchronously, revalidate on send, claim persistently before transport. */
final class Notification_Manager {
	/**
	 * Connect the collaborating services for this component.
	 *
	 * @param Notification_Repository $repository Repository.
	 * @param Recipient_Resolver      $resolver Resolver.
	 * @param Notification_Queue      $queue Queue.
	 * @param Logger                  $logger Logger.
	 */
	public function __construct( private Notification_Repository $repository, private Recipient_Resolver $resolver, private Notification_Queue $queue, private Logger $logger ) {}
	/**
	 * Schedule asynchronous recipient expansion for an episode.
	 *
	 * @param int $event_id Event id.
	 * @return void
	 */
	public function queue_event( int $event_id ): void {
		$this->queue->enqueue( $event_id );
	}
	/**
	 * Revalidate, claim and process one asynchronous episode or recipient job.
	 *
	 * @param int $event_id Event id.
	 * @param int $delivery_id Delivery id.
	 * @return void
	 */
	public function process( int $event_id, int $delivery_id = 0 ): void {
		$event = $this->repository->one( 'events', $event_id );
		if ( ! $event ) {
			return;
		}
		// Settings mutations acquire the same lock, so a committed policy change
		// cannot race the final eligibility check and transport invocation.
		Lock_Manager::run(
			'configuration',
			function () use ( $event, $delivery_id ) {
				Lock_Manager::run(
					'item:' . $event->item_id,
					function () use ( $event, $delivery_id ) {
						$settings = Settings::fresh();
						$event    = $this->repository->one( 'events', (int) $event->id );
						if ( ! $delivery_id && in_array( $event->status, array( 'accepted', 'failed', 'unknown', 'cancelled' ), true ) ) {
							return;
						}
						// Completed or ambiguous recipient claims cannot be reopened by a duplicate job.
						if ( $delivery_id ) {
							$claim = $this->repository->one( 'deliveries', $delivery_id );
							if ( ! $claim || (int) $claim->event_id !== (int) $event->id || 'queued' !== $claim->status || strtotime( $claim->next_at . ' UTC' ) > time() ) {
								return;
							}
						}
						$item = $this->repository->one( 'items', (int) $event->item_id );
						wp_cache_delete( (int) $event->item_id, 'post_meta' );
						$product = wc_get_product( $event->item_id );
						if ( ! $settings['enabled'] || (int) $event->revision !== $settings['revision'] || ! $item || (int) $item->episode !== (int) $event->episode || ! $product || 'trash' === $product->get_status() || ! $product->managing_stock() || (int) $product->get_stock_managed_by_id() !== (int) $event->item_id || ! is_numeric( $product->get_stock_quantity() ) || $product->get_stock_quantity() > $settings['threshold'] || ( ! $settings['out_of_stock'] && $product->get_stock_quantity() <= 0 ) || 'cancelled' === $event->status ) {
							$this->repository->update( 'events', array( 'status' => 'cancelled' ), array( 'id' => $event->id ) );
							foreach ( $this->repository->deliveries( (int) $event->id ) as $obsolete ) {
								if ( in_array( $obsolete->status, array( 'queued', 'paused', 'failed' ), true ) ) {
									$this->repository->update(
										'deliveries',
										array(
											'status'     => 'cancelled',
											'error_code' => 'episode_invalid',
										),
										array( 'id' => $obsolete->id )
									);
								}
							}
							$this->logger->log( 'notification_skipped', (int) $event->item_id, (int) $event->id, array( 'reason' => 'episode_or_configuration_invalid' ) );
							return;
						}
						$resolved = $this->resolver->resolve( (int) $event->product_id, $settings );
						if ( ! $resolved['recipients'] ) {
							if ( $delivery_id ) {
								$this->repository->update(
									'deliveries',
									array(
										'status'     => 'paused',
										'error_code' => 'recipient_unavailable',
										'updated_at' => Notification_Repository::now(),
									),
									array(
										'id'       => $delivery_id,
										'event_id' => $event->id,
										'status'   => 'queued',
									)
								);
							}
							$this->repository->update(
								'events',
								array(
									'status'   => 'unresolved',
									'retry_at' => gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS ),
								),
								array( 'id' => $event->id )
							);
							$this->repository->update( 'items', array( 'state' => 'unresolved' ), array( 'item_id' => $event->item_id ) );
							$this->logger->log(
								'recipient_unresolved',
								(int) $event->item_id,
								(int) $event->id,
								array(
									'provider' => $settings['provider'],
									'reason'   => $resolved['reason'],
								)
							);
							return;
						}
						$recipients = array();
						foreach ( $resolved['recipients'] as $recipient ) {
							$recipients[ $recipient->key() ] = $recipient;
						}
						if ( $settings['admin_copy'] ) {
							foreach ( $settings['admin_emails'] as $email ) {
								$copy = new Recipient( 0, __( 'Administrator', 'quixdevs-stocknotifier' ), $email, $settings['provider'], (int) $event->product_id, '', 'admin_copy' );
								if ( ! isset( $recipients[ $copy->key() ] ) ) {
										$recipients[ $copy->key() ] = $copy;
								}
							}
						}
						if ( ! $delivery_id ) {
							// Expand once. Existing snapshots are not replaced after ownership changes.
							if ( ! $this->repository->deliveries( (int) $event->id ) ) {
								$this->repository->transaction(
									function () use ( $event, $recipients ) {
										foreach ( $recipients as $recipient ) {
											$this->repository->insert(
												'deliveries',
												array(
													'event_id' => $event->id,
													'recipient_key' => $recipient->key(),
													'user_id' => $recipient->user_id,
													'email' => $recipient->email,
													'name' => $recipient->name,
													'store_name' => $recipient->store_name,
													'recipient_type' => $recipient->type,
													'status' => 'queued',
													'next_at' => Notification_Repository::now(),
													'updated_at' => Notification_Repository::now(),
												)
											);
										}
									}
								);
								$this->logger->log( 'recipient_resolved', (int) $event->item_id, (int) $event->id, array( 'provider' => $settings['provider'] ) );
							}
							foreach ( $this->repository->deliveries( (int) $event->id ) as $row ) {
								if ( in_array( $row->status, array( 'queued', 'paused' ), true ) ) {
										$this->repository->update( 'deliveries', array( 'status' => 'queued' ), array( 'id' => $row->id ) );
									if ( $this->queue->enqueue( (int) $event->id, (int) $row->id ) ) {
										$this->logger->log( 'email_queued', (int) $event->item_id, (int) $event->id, array( 'delivery_id' => (int) $row->id ) );
									}
								}
							}
							$this->repository->summarize( (int) $event->id );
							return;
						}
						$delivery = $this->repository->one( 'deliveries', $delivery_id );
						if ( ! $delivery || (int) $delivery->event_id !== (int) $event->id || 'queued' !== $delivery->status || strtotime( $delivery->next_at . ' UTC' ) > time() ) {
							return;
						}
						$recipient = $recipients[ $delivery->recipient_key ] ?? null;
						if ( ! $recipient || $recipient->user_id !== (int) $delivery->user_id || strtolower( $recipient->email ) !== strtolower( $delivery->email ) ) {
							$this->repository->update(
								'deliveries',
								array(
									'status'     => 'cancelled',
									'error_code' => 'ownership_changed',
								),
								array( 'id' => $delivery_id )
							);
							$this->repository->summarize( (int) $event->id );
							return;
						}
						$attempt  = (int) $delivery->attempts + 1;
						$renderer = new Template_Renderer();
						$values   = $renderer->values( $product, $recipient, (int) $event->threshold, $this->resolver );
						if ( 'admin_copy' === $recipient->type ) {
							$values['PRODUCT_EDIT_URL'] = $product->get_permalink();
						}
						$rendered = $renderer->render( $values, $settings );
						$this->repository->update(
							'deliveries',
							array(
								'status'     => 'sending',
								'attempts'   => $attempt,
								'updated_at' => Notification_Repository::now(),
							),
							array(
								'id'     => $delivery_id,
								'status' => 'queued',
							)
						);
						try {
							$accepted = ( new Email_Sender() )->send( $recipient->email, $rendered );
						} catch ( \Throwable $error ) {
							// Exceptions after transport invocation have an ambiguous outcome.
							$this->repository->update(
								'deliveries',
								array(
									'status'     => 'unknown',
									'error_code' => 'transport_interrupted',
									'updated_at' => Notification_Repository::now(),
								),
								array( 'id' => $delivery_id )
							);
							$this->repository->summarize( (int) $event->id );
							$this->logger->log(
								'delivery_unknown',
								(int) $event->item_id,
								(int) $event->id,
								array(
									'attempt'     => $attempt,
									'delivery_id' => $delivery_id,
								)
							);
							return;
						}
						$retry  = ! $accepted && $attempt <= $settings['retry_limit'];
						$delay  = min( 3600, 60 * ( 2 ** min( 6, $attempt - 1 ) ) );
						$status = $accepted ? 'accepted' : ( $retry ? 'queued' : 'failed' );
						$this->repository->update(
							'deliveries',
							array(
								'status'     => $status,
								'error_code' => $accepted ? '' : 'transport_rejected',
								'next_at'    => gmdate( 'Y-m-d H:i:s', time() + ( $retry ? $delay : 0 ) ),
								'updated_at' => Notification_Repository::now(),
							),
							array( 'id' => $delivery_id )
						);
						$this->repository->summarize( (int) $event->id );
						$this->logger->log(
							$accepted ? 'email_accepted' : 'email_failed',
							(int) $event->item_id,
							(int) $event->id,
							array(
								'attempt'     => $attempt,
								'delivery_id' => $delivery_id,
							)
						);
						// A current Scheduler action cannot schedule itself as unique; recovery
						// recreates the durable queued row after the current action completes.
						if ( $retry ) {
							$this->logger->log(
								'retry_scheduled',
								(int) $event->item_id,
								(int) $event->id,
								array(
									'attempt'     => $attempt,
									'delivery_id' => $delivery_id,
								)
							);
						}
					}
				);
			}
		);
	}
	/**
	 * Authorize a new queue attempt for an explicitly failed delivery.
	 *
	 * @param int $id Id.
	 * @return void
	 */
	public function retry( int $id ): void {
		Lock_Manager::run(
			'configuration',
			function () use ( $id ) {
				$delivery = $this->repository->one( 'deliveries', $id );
				if ( $delivery && 'failed' === $delivery->status ) {
					$event = $this->repository->one( 'events', (int) $delivery->event_id );
					if ( ! $event || Settings::get()['revision'] !== (int) $event->revision ) {
						return;
					}
					$this->repository->update(
						'deliveries',
						array(
							'status'  => 'queued',
							'next_at' => Notification_Repository::now(),
						),
						array(
							'id'     => $id,
							'status' => 'failed',
						)
					);
					$this->queue->enqueue( (int) $delivery->event_id, $id );
				}
			}
		);
	}
}
