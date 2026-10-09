<?php
/**
 * Notification Queue component.
 *
 * @package QuixDevsStockNotifier
 */

namespace QuixDevs\StockNotifier\Notifications;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use QuixDevs\StockNotifier\Dependencies;

/** Stable job arguments plus durable claims provide deduplication. */
final class Notification_Queue {
	public const HOOK  = 'quixdevs_stocknotifier_send_email';
	public const GROUP = 'quixdevs-stocknotifier';
	/**
	 * Queue one job per argument identity, protected independently of AS uniqueness.
	 *
	 * @param int $event_id Event id.
	 * @param int $delivery_id Delivery id.
	 * @param int $delay Delay.
	 * @return int
	 */
	public function enqueue( int $event_id, int $delivery_id = 0, int $delay = 0 ): int {
		if ( ! Dependencies::scheduler() ) {
			return 0; }
		return \QuixDevs\StockNotifier\Support\Lock_Manager::run(
			'job:' . $event_id . ':' . $delivery_id,
			static function () use ( $event_id, $delivery_id, $delay ) {
				$args = array(
					'event_id'    => $event_id,
					'delivery_id' => $delivery_id,
				);
				if ( as_has_scheduled_action( self::HOOK, $args, self::GROUP ) ) {
					return 0; }
				// The AS database store's unique flag is hook/group-wide, ignoring args.
				// Our connection lock and argument lookup permit distinct recipient jobs.
				return $delay > 0 ? (int) as_schedule_single_action( time() + $delay, self::HOOK, $args, self::GROUP, false ) : (int) as_enqueue_async_action( self::HOOK, $args, self::GROUP, false );
			}
		);
	}
}
