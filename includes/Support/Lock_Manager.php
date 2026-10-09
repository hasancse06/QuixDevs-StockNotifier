<?php
/**
 * Lock Manager component.
 *
 * @package QuixDevsStockNotifier
 */

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Durable operational records require current uncached reads; no WordPress object API exists for these owned tables.
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table identifiers and query fragments are fixed or allowlisted; all external values use prepare().

namespace QuixDevs\StockNotifier\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/** Connection-owned advisory locks never expire while an email is in flight. */
final class Lock_Manager {
	/**
	 * Execute a bounded operation under connection-owned concurrency protection.
	 *
	 * @param string   $key Key.
	 * @param callable $callback Callback.
	 * @throws \RuntimeException When validation or persistence cannot complete.
	 */
	public static function run( string $key, callable $callback ) {
		global $wpdb;
		$name = 'qsn_' . md5( DB_NAME . ':' . $wpdb->prefix . ':' . $key );
	 // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Connection-level concurrency control.
		$locked = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 3)', $name ) );
		if ( '1' !== (string) $locked ) {
			throw new \RuntimeException( 'StockNotifier lock unavailable; recovery will reconcile.' );
		}
		try {
			return $callback();
		} finally {
		 // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Release this connection's own advisory lock.
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) );
		}
	}
}
