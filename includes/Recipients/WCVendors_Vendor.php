<?php
/**
 * WCVendors Vendor component.
 *
 * @package QuixDevsStockNotifier
 */

namespace QuixDevs\StockNotifier\Recipients;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/** WC Vendors ownership resolution checked against the actual parent product. */
final class WCVendors_Vendor extends Provider {
	/**
	 * Return the stable provider identifier.
	 *
	 * @return string
	 */
	public function id(): string {
		return 'wcvendors'; }
	/**
	 * Return the translated provider label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'WC Vendors', 'quixdevs-stocknotifier' ); }
	/**
	 * Report integration API and configuration availability.
	 *
	 * @return string
	 */
	public function status(): string {
		if ( is_callable( array( '\WCV_Vendors', 'get_vendor_from_product' ) ) && is_callable( array( '\WCV_Vendors', 'is_vendor' ) ) ) {
			return 'available';
		}
		return $this->inactive_status( 'wc-vendors' );
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
		$vendor_id = (int) \WCV_Vendors::get_vendor_from_product( $product_id );
		if ( $owner < 1 || $vendor_id !== $owner || ! \WCV_Vendors::is_vendor( $vendor_id ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception data is escaped at Admin::notice or private preview output.
			throw new \RuntimeException( __( 'Product ownership is not a verified WC Vendors seller.', 'quixdevs-stocknotifier' ) );
		}
		$store = is_callable( array( '\WCV_Vendors', 'get_vendor_shop_name' ) ) ? (string) \WCV_Vendors::get_vendor_shop_name( $vendor_id ) : '';
		return array( $this->user( $vendor_id, $product_id, $store ) );
	}
	/**
	 * Resolve a destination the recipient can access.
	 *
	 * @param int       $product_id Product id.
	 * @param Recipient $recipient Recipient.
	 * @return string
	 */
	public function edit_url( int $product_id, Recipient $recipient ): string {
		$url = is_callable( array( '\WCV_Product_Controller', 'get_product_edit_link' ) ) ? \WCV_Product_Controller::get_product_edit_link( $product_id ) : '';
		return $this->safe_url( $url, $product_id );
	}
}
