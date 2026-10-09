<?php
/**
 * WordPress Author component.
 *
 * @package QuixDevsStockNotifier
 */

namespace QuixDevs\StockNotifier\Recipients;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/** The assigned WordPress post author, independent of optional integrations. */
final class WordPress_Author extends Provider {
	/**
	 * Return the stable provider identifier.
	 *
	 * @return string
	 */
	public function id(): string {
		return 'wordpress'; }
	/**
	 * Return the translated provider label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'WordPress Product Author', 'quixdevs-stocknotifier' ); }
	/**
	 * Report integration API and configuration availability.
	 *
	 * @return string
	 */
	public function status(): string {
		return 'available'; }
	/**
	 * Resolve eligible recipients from the explicit provider policy.
	 *
	 * @param int    $product_id Product id.
	 * @param string $policy Policy.
	 * @return array
	 */
	public function resolve( int $product_id, string $policy ): array {
		return array( $this->user( (int) get_post_field( 'post_author', $product_id ), $product_id ) );
	}
}
