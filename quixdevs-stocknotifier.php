<?php
/**
 * Plugin Name: QuixDevs StockNotifier
 * Description: Low Stock Alerts for WooCommerce. Monitor Stock. Alert Vendors. Stay Informed.
 * Version: 1.0.0
 * Author: QuixDevs
 * Author URI: https://quixdevs.com/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: quixdevs-stocknotifier
 * Domain Path: /languages
 * Requires at least: 6.8
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce
 * WC requires at least: 10.0
 * WC tested up to: 10.0
 *
 * @package QuixDevsStockNotifier
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
define( 'QUIXDEVS_STOCKNOTIFIER_FILE', __FILE__ );
require_once __DIR__ . '/includes/autoload.php';
register_activation_hook( __FILE__, array( QuixDevs\StockNotifier\Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( QuixDevs\StockNotifier\Deactivator::class, 'deactivate' ) );
add_action( 'plugins_loaded', array( QuixDevs\StockNotifier\Plugin::class, 'boot' ), 30 );
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);
