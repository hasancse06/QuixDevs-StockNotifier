<?php
/**
 * PublishPress Authors component.
 *
 * @package QuixDevsStockNotifier
 */

namespace QuixDevs\StockNotifier\Recipients;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/** Assigned editorial authors only; guest terms never become email destinations. */
final class PublishPress_Authors extends Provider {
	/**
	 * Return the stable provider identifier.
	 *
	 * @return string
	 */
	public function id(): string {
		return 'publishpress'; }
	/**
	 * Return the translated provider label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'PublishPress Authors / Pro', 'quixdevs-stocknotifier' ); }
	/**
	 * Report integration API and configuration availability.
	 *
	 * @return string
	 */
	public function status(): string {
		if ( ! function_exists( 'get_post_authors' ) || ! is_callable( array( '\MultipleAuthors\Classes\Utils', 'is_post_type_enabled' ) ) ) {
			return $this->inactive_status( 'publishpress-authors' );
		}
		return \MultipleAuthors\Classes\Utils::is_post_type_enabled( 'product' ) ? 'available' : 'unsupported';
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
		// Avoid API fallback that can create missing terms or editorial relationships.
		if ( ! has_term( '', 'author', $product_id ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception data is escaped at Admin::notice or private preview output.
			throw new \RuntimeException( __( 'No PublishPress authors are assigned to this product.', 'quixdevs-stocknotifier' ) );
		}
		$authors = get_post_authors( $product_id, true, false );
		$authors = $authors instanceof \WP_User ? array( $authors ) : (array) $authors;
		if ( 'primary' === $policy ) {
			$authors = array_slice( $authors, 0, 1 );
		}
		$recipients = array();
		foreach ( $authors as $author ) {
			$id = $author instanceof \WP_User ? (int) $author->ID : ( is_object( $author ) ? (int) ( $author->user_id ?? 0 ) : 0 );
			if ( $id > 0 ) {
				try {
					$recipient                       = $this->user( $id, $product_id );
					$recipients[ $recipient->key() ] = $recipient;
				} catch ( \InvalidArgumentException | \RuntimeException $error ) {
					// Continue other assigned registered authors; never use guest contact fields.
					continue;
				}
			}
		}
		if ( ! $recipients ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception data is escaped at Admin::notice or private preview output.
			throw new \RuntimeException( __( 'Assigned authors contain no eligible registered account with a valid email. Primary guests are skipped.', 'quixdevs-stocknotifier' ) );
		}
		return array_values( $recipients );
	}
}
