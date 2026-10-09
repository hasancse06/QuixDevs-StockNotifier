<?php
/**
 * Recipient component.
 *
 * @package QuixDevsStockNotifier
 */

namespace QuixDevs\StockNotifier\Recipients;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use QuixDevs\StockNotifier\Support\Validator;

/** Validated immutable recipient snapshot. Email is never taken from product metadata. */
final class Recipient {
	/**
	 * Connect the collaborating services for this component.
	 *
	 * @param int    $user_id User id.
	 * @param string $name Name.
	 * @param string $email Email.
	 * @param string $provider Provider.
	 * @param int    $product_id Product id.
	 * @param string $store_name Store name.
	 * @param string $type Type.
	 * @throws \InvalidArgumentException When validation or persistence cannot complete.
	 */
	public function __construct(
		public readonly int $user_id,
		public readonly string $name,
		public readonly string $email,
		public readonly string $provider,
		public readonly int $product_id,
		public readonly string $store_name = '',
		public readonly string $type = 'owner'
	) {
		if ( ! Validator::email( $email ) ) {
			throw new \InvalidArgumentException( 'Recipient email is invalid.' );
		}
	}
	/**
	 * Return the case-insensitive mailbox deduplication identity.
	 *
	 * @return string
	 */
	public function key(): string {
		return hash( 'sha256', strtolower( $this->email ) );
	}
}
