<?php
/**
 * Stock Monitor component.
 *
 * @package QuixDevsStockNotifier
 */

namespace QuixDevs\StockNotifier\Inventory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use QuixDevs\StockNotifier\Data\Notification_Repository;
use QuixDevs\StockNotifier\Support\{Settings, Lock_Manager, Logger};
use QuixDevs\StockNotifier\Notifications\Notification_Manager;

/** Observe canonical inventory and serialize episodes for each stock owner. */
final class Stock_Monitor {
	/**
	 * Connect the collaborating services for this component.
	 *
	 * @param Notification_Repository $repository Repository.
	 * @param Notification_Manager    $notifications Notifications.
	 * @param Logger                  $logger Logger.
	 */
	public function __construct( private Notification_Repository $repository, private Notification_Manager $notifications, private Logger $logger ) {}
	/**
	 * Register this component with the WordPress lifecycle.
	 *
	 * @return void
	 */
	public function hooks(): void {
		foreach ( array( 'woocommerce_product_before_set_stock', 'woocommerce_variation_before_set_stock' ) as $hook ) {
			add_action( $hook, array( $this, 'before' ), 1 );
		}
		foreach ( array( 'woocommerce_product_set_stock', 'woocommerce_variation_set_stock' ) as $hook ) {
			add_action( $hook, array( $this, 'changed' ), 30 );
		}
	}
	/**
	 * Establish a lazy baseline from persisted OLD quantity before any first update.
	 *
	 * @param mixed $product Product.
	 * @return void
	 */
	public function before( $product ): void {
		if ( ! $product instanceof \WC_Product || ! $product->get_id() ) {
			return;
		}
		$old = wc_get_product( $product->get_id() );
		if ( ! $old ) {
			return;
		}
		wp_cache_delete( $old->get_id(), 'post_meta' );
		$old->get_data_store()->read_stock_quantity( $old );
		$this->observe( $old, true );
	}
	/**
	 * Observe stock after a supported WooCommerce inventory update.
	 *
	 * @param mixed $product Product.
	 * @return void
	 */
	public function changed( $product ): void {
		if ( $product instanceof \WC_Product ) {
			$this->observe( $product );
		}
	}
	/**
	 * Load an inventory item after evicting stale post-meta cache.
	 *
	 * @param int $id Id.
	 */
	public function fresh( int $id ) {
		wp_cache_delete( $id, 'post_meta' );
		return wc_get_product( $id );
	}
	/**
	 * Resolve the actual stock-managed inventory owner.
	 *
	 * @param mixed $product Product.
	 */
	public function canonical( $product ) {
		if ( ! $product instanceof \WC_Product || ! $product->managing_stock() ) {
			return false;
		}
		$id = (int) $product->get_stock_managed_by_id();
		return $id === $product->get_id() ? $product : $this->fresh( $id );
	}
	/**
	 * Serialize monitoring transitions and create at most one eligible episode.
	 *
	 * @param mixed $product Product.
	 * @param bool  $baseline_only Baseline only.
	 * @param bool  $force Force.
	 * @return void
	 */
	public function observe( $product, bool $baseline_only = false, bool $force = false ): void {
		$source_id = $product instanceof \WC_Product ? $product->get_id() : 0;
		$product   = $this->canonical( $product );
		if ( ! $product || ! $product->managing_stock() || ! is_numeric( $product->get_stock_quantity() ) || 'trash' === $product->get_status() ) {
			if ( $source_id && $this->repository->one( 'items', $source_id ) ) {
				Lock_Manager::run(
					'item:' . $source_id,
					function () use ( $source_id ) {
						$this->repository->cancel_item( $source_id );
						$this->repository->update(
							'items',
							array(
								'state'    => 'disabled',
								'low'      => 0,
								'quantity' => null,
							),
							array( 'item_id' => $source_id )
						);
					}
				);
			}
			return;
		}
		$id = $product->get_id();
		// Switching a variation to shared stock retires its old independent monitor.
		if ( $source_id !== $id && $this->repository->one( 'items', $source_id ) ) {
			Lock_Manager::run(
				'item:' . $source_id,
				function () use ( $source_id ) {
					$this->repository->cancel_item( $source_id );
					$this->repository->update(
						'items',
						array(
							'state'    => 'disabled',
							'low'      => 0,
							'quantity' => null,
						),
						array( 'item_id' => $source_id )
					);
				}
			);
		}
		try {
			Lock_Manager::run(
				'item:' . $id,
				function () use ( $product, $id, $baseline_only, $force ) {
					if ( ! $baseline_only ) {
						$latest = $this->fresh( $id );
						if ( ! $latest || ! $latest->managing_stock() || (int) $latest->get_stock_managed_by_id() !== $id || ! is_numeric( $latest->get_stock_quantity() ) ) {
							$this->repository->cancel_item( $id );
							return;
						}
						$product = $latest;
					}
					$settings     = Settings::fresh();
					$quantity     = $product->get_stock_quantity();
					$low          = Alert_State::low( $quantity, $settings['threshold'] );
					$item         = $this->repository->one( 'items', $id );
					$new_baseline = ! $item || (int) $item->generation !== $settings['generation'] || 'disabled' === $item->state;
					if ( $baseline_only && ! $new_baseline ) {
						return;
					}
					$owner    = $product->is_type( 'variation' ) ? $product->get_parent_id() : $id;
					$state    = $low ? 'low' : 'healthy';
					$episode  = $item ? (int) $item->episode : 0;
					$crossed  = ! $new_baseline && $low && ! $item->low;
					$manual   = $force && $low && 0 === $episode;
					$eligible = $settings['enabled'] && ( $settings['out_of_stock'] || (float) $quantity > 0 );
					$event_id = $this->repository->transaction(
						function () use ( $id, $owner, $quantity, $settings, $low, $item, $state, $episode, $crossed, $manual, $eligible, $new_baseline ) {
							$event_id = 0;
							if ( ! $low || $new_baseline ) {
								$this->repository->cancel_item( $id );
							}
							$data = array(
								'item_id'    => $id,
								'product_id' => $owner,
								'quantity'   => $quantity,
								'threshold'  => $settings['threshold'],
								'generation' => $settings['generation'],
								'low'        => (int) $low,
								'episode'    => $episode,
								'state'      => $item && ! $new_baseline && $low && $item->low ? $item->state : $state,
								'updated_at' => Notification_Repository::now(),
							);
							if ( ! $settings['enabled'] ) {
										$data['state'] = 'disabled';
							}
							if ( $eligible && ( $crossed || $manual ) ) {
								$data['episode'] = $episode + 1;
								$data['state']   = 'unresolved';
								$event_id        = $this->repository->insert(
									'events',
									array(
										'item_id'    => $id,
										'product_id' => $owner,
										'episode'    => $data['episode'],
										'revision'   => $settings['revision'],
										'provider'   => $settings['provider'],
										'quantity'   => $quantity,
										'threshold'  => $settings['threshold'],
										'status'     => 'unresolved',
										'created_at' => Notification_Repository::now(),
									)
								);
							}
							if ( $item ) {
									$this->repository->update( 'items', $data, array( 'item_id' => $id ) );
							} else {
								$this->repository->insert( 'items', $data );
							}
							return $event_id;
						}
					);
					if ( $event_id ) {
							$this->logger->log(
								'threshold_crossed',
								$id,
								$event_id,
								array(
									'quantity'  => $quantity,
									'threshold' => $settings['threshold'],
									'provider'  => $settings['provider'],
								)
							);
							// Recipient expansion also happens asynchronously.
							$this->notifications->queue_event( $event_id );
					} elseif ( $item && $item->low && ! $low ) {
							$this->logger->log( 'alert_rearmed', $id );
					}
				}
			);
		} catch ( \Throwable $error ) {
			// Never break checkout because notification monitoring failed.
			if ( function_exists( 'wc_get_logger' ) ) {
				wc_get_logger()->error(
					'StockNotifier observation deferred to reconciliation.',
					array(
						'source'  => 'quixdevs-stocknotifier',
						'item_id' => $id,
					)
				);
			}
		}
	}
}
