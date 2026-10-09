<?php
/**
 * Validator component.
 *
 * @package QuixDevsStockNotifier
 */

namespace QuixDevs\StockNotifier\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/** Reject original invalid strings before normalization. */
final class Validator {
	public const MAX_THRESHOLD = 2147483647;
	/**
	 * Validate the original ASCII digit string and enforce the supported range.
	 *
	 * @param mixed $raw Raw.
	 * @return int
	 * @throws \InvalidArgumentException When validation or persistence cannot complete.
	 */
	public static function threshold( $raw ): int {
		if ( ! is_string( $raw ) || ! preg_match( '/\A[0-9]+\z/D', $raw ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception data is escaped at Admin::notice or private preview output.
			throw new \InvalidArgumentException( __( 'Use ASCII digits only, with no spaces, signs, decimals or exponents.', 'quixdevs-stocknotifier' ) );
		}
		$normalized = ltrim( $raw, '0' );
		$normalized = '' === $normalized ? '0' : $normalized;
		$max        = (string) min( PHP_INT_MAX, self::MAX_THRESHOLD );
		if ( strlen( $normalized ) > strlen( $max ) || ( strlen( $max ) === strlen( $normalized ) && strcmp( $normalized, $max ) > 0 ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception data is escaped at Admin::notice or private preview output.
			throw new \InvalidArgumentException( __( 'Threshold exceeds the supported maximum of 2147483647.', 'quixdevs-stocknotifier' ) );
		}
		return (int) $normalized;
	}
	/**
	 * Validate a mailbox and reject header-control characters.
	 *
	 * @param mixed $email Email.
	 * @return bool
	 */
	public static function email( $email ): bool {
		return is_string( $email ) && ! preg_match( '/[\r\n\x00]/', $email ) && trim( $email ) === $email && false !== is_email( $email );
	}
	/**
	 * Validate and deduplicate the administrator copy addresses.
	 *
	 * @param mixed $raw Raw.
	 * @return array
	 * @throws \InvalidArgumentException When validation or persistence cannot complete.
	 */
	public static function emails( $raw ): array {
		if ( ! is_string( $raw ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception data is escaped at Admin::notice or private preview output.
			throw new \InvalidArgumentException( __( 'Enter valid email addresses separated by commas.', 'quixdevs-stocknotifier' ) );
		}
		if ( '' === $raw ) {
			return array();
		}
		$emails = array_map( 'trim', explode( ',', $raw ) );
		if ( count( $emails ) > 10 ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception data is escaped at Admin::notice or private preview output.
			throw new \InvalidArgumentException( __( 'At most ten administrator copy addresses are allowed.', 'quixdevs-stocknotifier' ) );
		}
		foreach ( $emails as $email ) {
			if ( ! self::email( $email ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception data is escaped at Admin::notice or private preview output.
				throw new \InvalidArgumentException( __( 'An administrator email address is invalid.', 'quixdevs-stocknotifier' ) );
			}
		}
		return array_values( array_unique( array_map( 'strtolower', $emails ) ) );
	}
}
