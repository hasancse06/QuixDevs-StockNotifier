<?php
/**
 * Optional site-local cleanup only.
 *
 * @package QuixDevsStockNotifier
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
$quixdevs_stocknotifier_settings = get_option( 'quixdevs_stocknotifier_settings', array() );
if ( empty( $quixdevs_stocknotifier_settings['delete_data'] ) ) {
	return;
}
global $wpdb;
foreach ( array( 'items', 'events', 'deliveries', 'activity' ) as $quixdevs_stocknotifier_table ) {
 // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL -- Fixed plugin-owned table allowlist.
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'quixdevs_stocknotifier_' . $quixdevs_stocknotifier_table ) );
}
// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Enumerate only owned options during explicit uninstall cleanup.
$quixdevs_stocknotifier_options = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'quixdevs_stocknotifier_' ) . '%' ) );
foreach ( $quixdevs_stocknotifier_options as $quixdevs_stocknotifier_option ) {
	delete_option( $quixdevs_stocknotifier_option );
}
if ( function_exists( 'as_unschedule_all_actions' ) ) {
	foreach ( array( 'quixdevs_stocknotifier_send_email', 'quixdevs_stocknotifier_reconcile', 'quixdevs_stocknotifier_tool_batch', 'quixdevs_stocknotifier_test_email' ) as $quixdevs_stocknotifier_hook ) {
		as_unschedule_all_actions( $quixdevs_stocknotifier_hook, null, 'quixdevs-stocknotifier' );
	}
}
