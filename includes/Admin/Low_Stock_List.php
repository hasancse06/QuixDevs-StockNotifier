<?php
/**
 * Low Stock List component.
 *
 * @package QuixDevsStockNotifier
 */

namespace QuixDevs\StockNotifier\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use QuixDevs\StockNotifier\Data\Notification_Repository;
use QuixDevs\StockNotifier\Recipients\Recipient_Resolver;

/** Bounded inventory table with safe filters and sorting. */
final class Low_Stock_List {
	/**
	 * Render escaped native admin output or sanitized email content.
	 *
	 * @param Notification_Repository $repository Repository.
	 * @param Recipient_Resolver      $resolver Resolver.
	 * @return void
	 */
	public function render( Notification_Repository $repository, Recipient_Resolver $resolver ): void {
		$page    = max( 1, absint( $_GET['paged'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$filter  = sanitize_key( $_GET['filter'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search  = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$sort    = sanitize_key( $_GET['orderby'] ?? 'item_id' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$labels  = array(
			''           => __( 'All Monitored', 'quixdevs-stocknotifier' ),
			'low'        => __( 'Low Stock', 'quixdevs-stocknotifier' ),
			'out'        => __( 'Out of Stock', 'quixdevs-stocknotifier' ),
			'accepted'   => __( 'Notified', 'quixdevs-stocknotifier' ),
			'queued'     => __( 'Pending', 'quixdevs-stocknotifier' ),
			'failed'     => __( 'Failed', 'quixdevs-stocknotifier' ),
			'unresolved' => __( 'Unresolved Recipient', 'quixdevs-stocknotifier' ),
		);
		$filter  = isset( $labels[ $filter ] ) ? $filter : '';
		$results = $repository->page( 'items', $filter, $page, $search, $sort );
		echo '<form method="get"><input type="hidden" name="page" value="quixdevs-stocknotifier-products"><label class="screen-reader-text" for="qsn-filter">' . esc_html__( 'Filter inventory', 'quixdevs-stocknotifier' ) . '</label><select id="qsn-filter" name="filter">';
		foreach ( $labels as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $filter, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select> <label class="screen-reader-text" for="qsn-search">' . esc_html__( 'Search by product name, SKU or ID', 'quixdevs-stocknotifier' ) . '</label><input id="qsn-search" type="search" name="s" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Product name, SKU or ID', 'quixdevs-stocknotifier' ) . '"> <label for="qsn-sort">' . esc_html__( 'Sort by', 'quixdevs-stocknotifier' ) . '</label><select name="orderby" id="qsn-sort">';
		foreach ( array(
			'item_id'    => __( 'Product ID', 'quixdevs-stocknotifier' ),
			'quantity'   => __( 'Stock Quantity', 'quixdevs-stocknotifier' ),
			'updated_at' => __( 'Updated', 'quixdevs-stocknotifier' ),
			'state'      => __( 'State', 'quixdevs-stocknotifier' ),
		) as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $sort, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select> <button class="button">' . esc_html__( 'Apply', 'quixdevs-stocknotifier' ) . '</button></form><table class="widefat striped qsn-table"><thead><tr>';
		foreach ( array( __( 'Product', 'quixdevs-stocknotifier' ), __( 'SKU', 'quixdevs-stocknotifier' ), __( 'Stock Quantity', 'quixdevs-stocknotifier' ), __( 'Threshold', 'quixdevs-stocknotifier' ), __( 'Product Owner/Vendor', 'quixdevs-stocknotifier' ), __( 'Provider', 'quixdevs-stocknotifier' ), __( 'Last Alert', 'quixdevs-stocknotifier' ), __( 'Notification State', 'quixdevs-stocknotifier' ), __( 'Actions', 'quixdevs-stocknotifier' ) ) as $label ) {
			echo '<th scope="col">' . esc_html( $label ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $results['rows'] as $row ) {
			$product  = wc_get_product( $row->item_id );
			$resolved = $resolver->resolve( (int) $row->product_id );
			echo '<tr><td>' . esc_html( $product ? $product->get_name() : '#' . $row->item_id ) . '</td><td>' . esc_html( $product ? $product->get_sku() : '' ) . '</td><td>' . esc_html( null === $row->quantity ? '—' : wc_format_decimal( $row->quantity ) ) . '</td><td>' . esc_html( $row->threshold ) . '</td><td>' . esc_html( $resolved['recipients'] ? implode( ', ', array_map( static fn( $r ) => $r->name, $resolved['recipients'] ) ) : $resolved['reason'] ) . '</td><td>' . esc_html( \QuixDevs\StockNotifier\Support\Settings::get()['provider'] ) . '</td><td>' . esc_html( $repository->last_alert( (int) $row->item_id, (int) $row->episode ) ? get_date_from_gmt( $repository->last_alert( (int) $row->item_id, (int) $row->episode ), 'Y-m-d H:i:s' ) : '—' ) . '</td><td>' . esc_html( View::status( $row->state ) ) . '</td><td>';
			if ( $product ) {
				echo '<a href="' . esc_url( get_permalink( $row->product_id ) ) . '">' . esc_html__( 'View Product', 'quixdevs-stocknotifier' ) . '</a><br>';
			}
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=quixdevs-stocknotifier-tools&product_id=' . $row->item_id ) ) . '">' . esc_html__( 'Preview Recipient', 'quixdevs-stocknotifier' ) . '</a><br><a href="' . esc_url( admin_url( 'admin.php?page=quixdevs-stocknotifier-activity' ) ) . '">' . esc_html__( 'View Activity / Retry', 'quixdevs-stocknotifier' ) . '</a></td></tr>';
		}
		if ( ! $results['rows'] ) {
			echo '<tr><td colspan="9">' . esc_html__( 'No monitored items match. Initial baselines are built in the background.', 'quixdevs-stocknotifier' ) . '</td></tr>';
		}
		echo '</tbody></table>';
		View::pagination( $results['total'], $page );
	}
}
