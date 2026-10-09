<?php
/**
 * Tools Page component.
 *
 * @package QuixDevsStockNotifier
 */

namespace QuixDevs\StockNotifier\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use QuixDevs\StockNotifier\Recipients\Recipient_Resolver;

/** Diagnostics are read-only; every manual bulk mutation requires confirmation. */
final class Tools_Page {
	/**
	 * Render escaped native admin output or sanitized email content.
	 *
	 * @return void
	 */
	public function render(): void {
		echo '<h2>' . esc_html__( 'Preview Notification Recipient', 'quixdevs-stocknotifier' ) . '</h2>';
		View::form( 'recipient' );
		$id = absint( $_GET['product_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Populate private form only.
		echo '<p><label for="qsn-product-search">' . esc_html__( 'Select/Search WooCommerce Product', 'quixdevs-stocknotifier' ) . '</label></p><input id="qsn-product-search" type="search" autocomplete="off" aria-controls="qsn-product-results"><select id="qsn-product-results" name="product_id" aria-label="' . esc_attr__( 'Matching products', 'quixdevs-stocknotifier' ) . '" required>';
		if ( $id && wc_get_product( $id ) ) {
			echo '<option value="' . esc_attr( (string) $id ) . '">' . esc_html( wc_get_product( $id )->get_name() ) . '</option>';
		}
		echo '</select><p id="qsn-search-status" aria-live="polite"></p>';
		View::button( __( 'Preview Recipient', 'quixdevs-stocknotifier' ) );
		$preview = get_transient( 'quixdevs_stocknotifier_recipient_' . get_current_user_id() );
		if ( $preview ) {
			echo '<div class="qsn-preview"><p><strong>' . esc_html__( 'Product:', 'quixdevs-stocknotifier' ) . '</strong> ' . esc_html( $preview['product'] ) . '</p><p>' . esc_html__( 'Recipient Provider:', 'quixdevs-stocknotifier' ) . ' ' . esc_html( $preview['provider'] ) . '</p><p>' . esc_html( $preview['reason'] ) . '</p>';
			foreach ( $preview['recipients'] as $r ) {
				echo '<p>' . esc_html( $r['name'] . ' — ' . $r['email'] ) . ' — ' . esc_html__( 'Valid', 'quixdevs-stocknotifier' ) . '</p>';
			}
			echo '</div>';
		}
		echo '<h2>' . esc_html__( 'Existing Low-Stock Products', 'quixdevs-stocknotifier' ) . '</h2><p>' . esc_html__( 'A background preview counts affected products, recipients and estimated messages. Each confirmed run queues at most 500 previously unalerted inventory items. Counts can change before delivery.', 'quixdevs-stocknotifier' ) . '</p>';
		View::form( 'preview_existing' );
		View::button( __( 'Preview Existing Low-Stock Products', 'quixdevs-stocknotifier' ) );
		$run = get_option( 'quixdevs_stocknotifier_tool_run' );
		if ( $run ) {
			echo '<div role="status"><p>' . esc_html( $run['mode'] . ': ' . $run['status'] ) . '</p><ul>';
			foreach ( array(
				'affected'   => __( 'Affected product count', 'quixdevs-stocknotifier' ),
				'resolved'   => __( 'Resolved recipient count', 'quixdevs-stocknotifier' ),
				'unresolved' => __( 'Unresolved recipient count', 'quixdevs-stocknotifier' ),
				'estimated'  => __( 'Estimated notification count', 'quixdevs-stocknotifier' ),
			) as $key => $label ) {
				echo '<li>' . esc_html( $label . ': ' . $run[ $key ] ) . '</li>';
			}
			echo '</ul></div>';
			if ( 'preview' === $run['mode'] && 'complete' === $run['status'] && get_current_user_id() === $run['user_id'] && $run['expires'] >= time() ) {
				View::form( 'notify_existing' );
				echo '<input type="hidden" name="run_id" value="' . esc_attr( $run['id'] ) . '"><label><input type="checkbox" name="confirm" value="1" required> ' . esc_html__( 'I confirm the previewed notification campaign.', 'quixdevs-stocknotifier' ) . '</label>';
				View::button( __( 'Notify Existing Low-Stock Products', 'quixdevs-stocknotifier' ) );
			}
		}
		echo '<h2>' . esc_html__( 'Recovery', 'quixdevs-stocknotifier' ) . '</h2>';
		foreach ( array(
			'repair'    => __( 'Repair Missing Notification Jobs', 'quixdevs-stocknotifier' ),
			'retry_all' => __( 'Retry Failed Notifications', 'quixdevs-stocknotifier' ),
			'baseline'  => __( 'Refresh Monitoring Baselines', 'quixdevs-stocknotifier' ),
		) as $task => $label ) {
			View::form( $task );
			echo '<label><input type="checkbox" name="confirm" value="1" required> ' . esc_html__( 'Confirm background operation', 'quixdevs-stocknotifier' ) . '</label>';
			View::button( $label );
		}
		echo '<p>' . esc_html__( 'Baseline refresh initializes missing or outdated-generation observations without sending. Existing episodes remain protected. After a threshold change, run a fresh preview before a manual campaign.', 'quixdevs-stocknotifier' ) . '</p><p><a href="' . esc_url( admin_url( 'admin.php?page=quixdevs-stocknotifier-activity&status=failed' ) ) . '">' . esc_html__( 'Inspect Failed Jobs', 'quixdevs-stocknotifier' ) . '</a></p>';
		Dashboard::health();
	}
}
