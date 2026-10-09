<?php
/**
 * Dashboard component.
 *
 * @package QuixDevsStockNotifier
 */

namespace QuixDevs\StockNotifier\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use QuixDevs\StockNotifier\Data\Notification_Repository;
use QuixDevs\StockNotifier\Dependencies;

/** Aggregate SQL counters, never a product-object catalog scan. */
final class Dashboard {
	/**
	 * Render escaped native admin output or sanitized email content.
	 *
	 * @param Notification_Repository $repository Repository.
	 * @return void
	 */
	public function render( Notification_Repository $repository ): void {
		$stats  = $repository->stats();
		$labels = array(
			'monitored'    => __( 'Monitored inventory items', 'quixdevs-stocknotifier' ),
			'low'          => __( 'Currently low-stock items', 'quixdevs-stocknotifier' ),
			'out_of_stock' => __( 'Out-of-stock items', 'quixdevs-stocknotifier' ),
			'episodes'     => __( 'Alert episodes created (retained history)', 'quixdevs-stocknotifier' ),
			'queued'       => __( 'Pending notifications', 'quixdevs-stocknotifier' ),
			'accepted'     => __( 'Accepted by mail transport', 'quixdevs-stocknotifier' ),
			'failed'       => __( 'Failed notifications', 'quixdevs-stocknotifier' ),
			'unresolved'   => __( 'Unresolved recipients', 'quixdevs-stocknotifier' ),
			'unknown'      => __( 'Delivery unknown', 'quixdevs-stocknotifier' ),
		);
		echo '<div class="qsn-cards">';
		foreach ( $labels as $key => $label ) {
			echo '<div class="qsn-card"><strong>' . esc_html( (string) ( $stats[ $key ] ?? 0 ) ) . '</strong><span>' . esc_html( $label ) . '</span></div>';
		}
		echo '</div>';
		self::health();
		echo '<h2>' . esc_html__( 'Recent Notification Activity', 'quixdevs-stocknotifier' ) . '</h2>';
		( new Activity_Page() )->render( $repository, false );
	}
	/**
	 * Display dependencies, reconciliation health and scheduler navigation.
	 *
	 * @return void
	 */
	public static function health(): void {
		echo '<h2>' . esc_html__( 'Scheduling Health', 'quixdevs-stocknotifier' ) . '</h2><ul>';
		echo '<li>' . esc_html__( 'WooCommerce:', 'quixdevs-stocknotifier' ) . ' ' . esc_html( Dependencies::woocommerce() ? __( 'Available', 'quixdevs-stocknotifier' ) : __( 'Unavailable', 'quixdevs-stocknotifier' ) ) . '</li>';
		echo '<li>' . esc_html__( 'Action Scheduler:', 'quixdevs-stocknotifier' ) . ' ' . esc_html( Dependencies::scheduler() ? __( 'Available', 'quixdevs-stocknotifier' ) : __( 'Unavailable — jobs are preserved until recovery', 'quixdevs-stocknotifier' ) ) . '</li>';
		$last = (int) get_option( 'quixdevs_stocknotifier_last_reconciliation', 0 );
		echo '<li>' . esc_html__( 'Last reconciliation:', 'quixdevs-stocknotifier' ) . ' ' . esc_html( $last ? wp_date( 'Y-m-d H:i:s', $last ) : __( 'Not run yet', 'quixdevs-stocknotifier' ) ) . '</li></ul>';
		if ( ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) || ( $last && time() - $last > 600 ) ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'WP-Cron is disabled or processing appears delayed. Configure a real cron runner for timely notifications.', 'quixdevs-stocknotifier' ) . '</p></div>';
		}
		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=wc-status&tab=action-scheduler&s=quixdevs_stocknotifier' ) ) . '">' . esc_html__( 'Inspect pending and failed Action Scheduler jobs', 'quixdevs-stocknotifier' ) . '</a></p>';
	}
}
