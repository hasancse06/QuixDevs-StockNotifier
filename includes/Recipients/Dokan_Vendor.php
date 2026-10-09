<?php
/**
 * Dokan Vendor component.
 *
 * @package QuixDevsStockNotifier
 */

namespace QuixDevs\StockNotifier\Recipients;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/** Public Dokan ownership and selling eligibility APIs; staff are excluded. */
final class Dokan_Vendor extends Provider {
	/**
	 * Return the stable provider identifier.
	 *
	 * @return string
	 */
	public function id(): string {
		return 'dokan'; }
	/**
	 * Return the translated provider label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Dokan Vendor', 'quixdevs-stocknotifier' ); }
	/**
	 * Report integration API and configuration availability.
	 *
	 * @return string
	 */
	public function status(): string {
		if ( function_exists( 'dokan_get_vendor' ) && function_exists( 'dokan_is_user_seller' ) && function_exists( 'dokan_get_vendor_by_product' ) ) {
			return 'available';
		}
		return $this->inactive_status( 'dokan-lite' );
	}
	/**
	 * Resolve eligible recipients from the explicit provider policy.
	 *
	 * @param int    $product_id Product id.
	 * @param string $policy Policy.
	 * @return array
	 * @throws \RuntimeException When validation or persistence cannot complete.
	 */
	public function resolve( int $product_id, string $policy ): array {
		$owner     = (int) get_post_field( 'post_author', $product_id );
		$vendor_id = (int) dokan_get_vendor_by_product( $product_id, true );
		if ( $owner < 1 || $owner !== $vendor_id || ! dokan_is_user_seller( $vendor_id, true ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception data is escaped at Admin::notice or private preview output.
			throw new \RuntimeException( __( 'Product ownership is not a verified Dokan seller.', 'quixdevs-stocknotifier' ) );
		}
		$vendor = dokan_get_vendor( $vendor_id );
		if ( ! is_object( $vendor ) || ! is_callable( array( $vendor, 'get_id' ) ) || ! is_callable( array( $vendor, 'is_enabled' ) ) || (int) $vendor->get_id() !== $vendor_id || ! $vendor->is_enabled() ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception data is escaped at Admin::notice or private preview output.
			throw new \RuntimeException( __( 'Dokan vendor is unavailable or selling is disabled.', 'quixdevs-stocknotifier' ) );
		}
		return array( $this->user( $vendor_id, $product_id, is_callable( array( $vendor, 'get_shop_name' ) ) ? (string) $vendor->get_shop_name() : '' ) );
	}
	/**
	 * Resolve a destination the recipient can access.
	 *
	 * @param int       $product_id Product id.
	 * @param Recipient $recipient Recipient.
	 * @return string
	 */
	public function edit_url( int $product_id, Recipient $recipient ): string {
		// Session-bound edit nonces generated in a worker are unusable by the vendor.
		// Link to the supported dashboard list, where the vendor can open their editor.
		$url = function_exists( 'dokan_get_navigation_url' ) ? dokan_get_navigation_url( 'products' ) : '';
		return $this->safe_url( $url, $product_id );
	}
}
