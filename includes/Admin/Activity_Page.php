<?php
/**
 * Activity Page component.
 *
 * @package QuixDevsStockNotifier
 */

namespace QuixDevs\StockNotifier\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use QuixDevs\StockNotifier\Data\Notification_Repository;

/** Private, paginated recipient outcomes and diagnostics. */
final class Activity_Page {
	/**
	 * Render escaped native admin output or sanitized email content.
	 *
	 * @param Notification_Repository $repository Repository.
	 * @param bool                    $full Full.
	 * @return void
	 */
	public function render( Notification_Repository $repository, bool $full = true ): void {
		$page   = $full ? max( 1, absint( $_GET['paged'] ?? 1 ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination.
		$status = $full ? sanitize_key( $_GET['status'] ?? '' ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter.
		$events = $repository->page( 'events', $status, $page );
		echo '<table class="widefat striped"><thead><tr>';
		foreach ( array( __( 'Product', 'quixdevs-stocknotifier' ), __( 'SKU', 'quixdevs-stocknotifier' ), __( 'Stock / Threshold', 'quixdevs-stocknotifier' ), __( 'Recipient / Provider', 'quixdevs-stocknotifier' ), __( 'Notification Status', 'quixdevs-stocknotifier' ), __( 'Date/Time', 'quixdevs-stocknotifier' ) ) as $label ) {
			echo '<th scope="col">' . esc_html( $label ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $events['rows'] as $event ) {
			$product = wc_get_product( $event->item_id );
			echo '<tr><td>' . esc_html( $product ? $product->get_name() : '#' . $event->item_id ) . '</td><td>' . esc_html( $product ? $product->get_sku() : '' ) . '</td><td>' . esc_html( wc_format_decimal( $event->quantity ) . ' / ' . $event->threshold ) . '</td><td>';
			$deliveries = $repository->deliveries( (int) $event->id );
			foreach ( $deliveries as $delivery ) {
				echo '<p>' . esc_html( $delivery->name . ' <' . $delivery->email . '> — ' . View::status( $delivery->status ) . ' (' . $delivery->attempts . ')' ) . '</p>';
				if ( $full && 'failed' === $delivery->status ) {
					View::form( 'retry' );
					echo '<input type="hidden" name="delivery_id" value="' . esc_attr( $delivery->id ) . '"><label><input type="checkbox" name="confirm" value="1" required> ' . esc_html__( 'Confirm retry', 'quixdevs-stocknotifier' ) . '</label>';
					View::button( __( 'Retry Failed Notification', 'quixdevs-stocknotifier' ) );
				}
			}
			echo '<p>' . esc_html( $event->provider ) . '</p></td><td>' . esc_html( View::status( $event->status ) ) . '</td><td>' . esc_html( get_date_from_gmt( $event->created_at, 'Y-m-d H:i:s' ) ) . '</td></tr>';
		}
		if ( ! $events['rows'] ) {
			echo '<tr><td colspan="6">' . esc_html__( 'No alert episodes yet.', 'quixdevs-stocknotifier' ) . '</td></tr>';
		}
		echo '</tbody></table>';
		if ( $full ) {
			View::pagination( $events['total'], $page );
			echo '<h2>' . esc_html__( 'Diagnostic Entries', 'quixdevs-stocknotifier' ) . '</h2><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Item / Episode', 'quixdevs-stocknotifier' ) . '</th><th>' . esc_html__( 'Action / Context', 'quixdevs-stocknotifier' ) . '</th><th>' . esc_html__( 'Date/Time', 'quixdevs-stocknotifier' ) . '</th></tr></thead><tbody>';
			foreach ( $repository->page( 'activity', '', $page )['rows'] as $row ) {
				echo '<tr><td>' . esc_html( $row->item_id . ' / ' . $row->event_id ) . '</td><td>' . esc_html( $row->code . ' ' . $row->context ) . '</td><td>' . esc_html( get_date_from_gmt( $row->created_at, 'Y-m-d H:i:s' ) ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}
	}
}
