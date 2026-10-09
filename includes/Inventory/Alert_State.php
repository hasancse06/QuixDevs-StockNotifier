<?php
/**
 * Alert State component.
 *
 * @package QuixDevsStockNotifier
 */

namespace QuixDevs\StockNotifier\Inventory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/** Numeric stock alone determines low status, including backorders. */
final class Alert_State {
	/**
	 * Compare actual numeric inventory against the threshold.
	 *
	 * @param mixed $quantity Quantity.
	 * @param int   $threshold Threshold.
	 * @return bool
	 */
	public static function low( $quantity, int $threshold ): bool {
		return is_numeric( $quantity ) && (float) $quantity <= $threshold;
	}
	/**
	 * Determine whether a numeric observation crosses or rearms an episode.
	 *
	 * @param bool  $was_low Was low.
	 * @param mixed $quantity Quantity.
	 * @param int   $threshold Threshold.
	 * @return string
	 */
	public static function transition( bool $was_low, $quantity, int $threshold ): string {
		if ( ! is_numeric( $quantity ) ) {
			return 'disabled';
		}
		$low = self::low( $quantity, $threshold );
		return $low ? ( $was_low ? 'awaiting_restock' : 'crossed' ) : ( $was_low ? 'rearmed' : 'healthy' );
	}
}
