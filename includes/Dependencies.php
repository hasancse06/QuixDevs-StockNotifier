<?php
/**
 * Dependencies component.
 *
 * @package QuixDevsStockNotifier
 */

namespace QuixDevs\StockNotifier;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/** Runtime dependency checks, including Scheduler initialization. */
final class Dependencies {
	/**
	 * Check the supported WooCommerce runtime dependency.
	 *
	 * @return bool
	 */
	public static function woocommerce(): bool {
		return class_exists( 'WooCommerce' ) && function_exists( 'wc_get_product' ) && version_compare( WC_VERSION, '10.0', '>=' );
	}
	/**
	 * Check that the Action Scheduler data store is initialized.
	 *
	 * @return bool
	 */
	public static function scheduler(): bool {
		return function_exists( 'as_enqueue_async_action' ) && class_exists( 'ActionScheduler' ) && \ActionScheduler::is_initialized();
	}
}
