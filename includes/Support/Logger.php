<?php
/**
 * Logger component.
 *
 * @package QuixDevsStockNotifier
 */

namespace QuixDevs\StockNotifier\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use QuixDevs\StockNotifier\Data\Notification_Repository;

/** Private diagnostic history, without email addresses or message bodies. */
final class Logger {
	/**
	 * Connect the collaborating services for this component.
	 *
	 * @param Notification_Repository $repository Repository.
	 */
	public function __construct( private Notification_Repository $repository ) {}
	/**
	 * Record a bounded private diagnostic context without message bodies.
	 *
	 * @param string $code Code.
	 * @param int    $item_id Item id.
	 * @param int    $event_id Event id.
	 * @param array  $context Context.
	 * @return void
	 */
	public function log( string $code, int $item_id = 0, int $event_id = 0, array $context = array() ): void {
		if ( ! Settings::get()['logging'] ) {
			return;
		}
		$context = array_intersect_key( $context, array_flip( array( 'quantity', 'threshold', 'provider', 'attempt', 'reason', 'revision', 'delivery_id' ) ) );
		$this->repository->insert(
			'activity',
			array(
				'item_id'    => $item_id,
				'event_id'   => $event_id,
				'provider'   => $context['provider'] ?? '',
				'code'       => $code,
				'context'    => wp_json_encode( $context ),
				'created_at' => Notification_Repository::now(),
			)
		);
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->info(
				$code,
				array(
					'source'   => 'quixdevs-stocknotifier',
					'item_id'  => $item_id,
					'event_id' => $event_id,
				) + $context
			);
		}
	}
}
