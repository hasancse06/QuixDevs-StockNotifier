<?php
/**
 * Activator component.
 *
 * @package QuixDevsStockNotifier
 */

namespace QuixDevs\StockNotifier;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use QuixDevs\StockNotifier\Support\Settings;
use QuixDevs\StockNotifier\Data\Notification_Repository;

/** Activation establishes baselines asynchronously and never sends a campaign. */
final class Activator {
	/**
	 * Initialize plugin-owned storage and safe monitoring defaults.
	 *
	 * @param bool $network_wide Network wide.
	 * @return void
	 */
	public static function activate( bool $network_wide = false ): void {
		if ( $network_wide ) {
			wp_die( esc_html__( 'Activate StockNotifier individually on each site.', 'quixdevs-stocknotifier' ) );
		}
		if ( ! Dependencies::woocommerce() ) {
			wp_die( esc_html__( 'StockNotifier requires active WooCommerce 10.0 or newer.', 'quixdevs-stocknotifier' ) );
		}
		( new Notification_Repository() )->install();
		add_option( Settings::OPTION, Settings::defaults(), '', false );
		add_option( 'quixdevs_stocknotifier_installed_at', time(), '', false );
		add_option( 'quixdevs_stocknotifier_scan_cursor', 0, '', false );
	}
}
