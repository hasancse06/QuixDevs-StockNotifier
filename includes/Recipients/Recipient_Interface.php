<?php
/**
 * Recipient Interface component.
 *
 * @package QuixDevsStockNotifier
 */

namespace QuixDevs\StockNotifier\Recipients;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/** A provider either resolves its own recipients or fails closed. */
interface Recipient_Interface {
	/**
	 * Return the stable provider identifier.
	 *
	 * @return string
	 */
	public function id(): string;
	/**
	 * Return the translated provider label.
	 *
	 * @return string
	 */
	public function label(): string;
	/**
	 * Report integration API and configuration availability.
	 *
	 * @return string
	 */
	public function status(): string;
	/**
	 * Resolve eligible recipients from the explicit provider policy.
	 *
	 * @param int    $product_id Product id.
	 * @param string $policy Policy.
	 * @return array
	 */
	public function resolve( int $product_id, string $policy ): array;
	/**
	 * Resolve a destination the recipient can access.
	 *
	 * @param int       $product_id Product id.
	 * @param Recipient $recipient Recipient.
	 * @return string
	 */
	public function edit_url( int $product_id, Recipient $recipient ): string;
}
