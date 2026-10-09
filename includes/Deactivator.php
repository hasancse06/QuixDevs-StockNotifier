<?php
/**
 * Deactivator component.
 *
 * @package QuixDevsStockNotifier
 */

namespace QuixDevs\StockNotifier;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/** Stop owned processing, retain durable pending records for reactivation. */
final class Deactivator {
	/**
	 * Stop owned scheduled work while preserving durable history.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		if ( Dependencies::scheduler() ) {
			foreach ( array( 'quixdevs_stocknotifier_send_email', 'quixdevs_stocknotifier_reconcile', 'quixdevs_stocknotifier_tool_batch', 'quixdevs_stocknotifier_test_email' ) as $hook ) {
				as_unschedule_all_actions( $hook, null, 'quixdevs-stocknotifier' );
			}
		}
		delete_option( 'quixdevs_stocknotifier_tool_run' );
	}
}
