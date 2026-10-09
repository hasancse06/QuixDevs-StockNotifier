<?php
/**
 * Provider component.
 *
 * @package QuixDevsStockNotifier
 */

namespace QuixDevs\StockNotifier\Recipients;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/** Shared account validation and plugin inventory checks. */
abstract class Provider implements Recipient_Interface {
	/**
	 * Build a recipient from a verified WordPress account.
	 *
	 * @param int    $id Id.
	 * @param int    $product_id Product id.
	 * @param string $store Store.
	 * @return Recipient
	 * @throws \RuntimeException When validation or persistence cannot complete.
	 */
	protected function user( int $id, int $product_id, string $store = '' ): Recipient {
		$user = $id > 0 ? get_userdata( $id ) : false;
		if ( ! $user instanceof \WP_User || ! $user->exists() ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception data is escaped at Admin::notice or private preview output.
			throw new \RuntimeException( __( 'No eligible WordPress account exists.', 'quixdevs-stocknotifier' ) );
		}
		return new Recipient( $id, $user->display_name, $user->user_email, $this->id(), $product_id, $store );
	}
	/**
	 * Distinguish an absent integration from inactive or unsupported installs.
	 *
	 * @param string $slug Slug.
	 * @return string
	 */
	protected function inactive_status( string $slug ): string {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		foreach ( array_keys( get_plugins() ) as $file ) {
			if ( str_starts_with( $file, $slug . '/' ) ) {
				return is_plugin_active( $file ) ? 'unsupported' : 'inactive';
			}
		}
		return 'not_installed';
	}
	/**
	 * Resolve a destination the recipient can access.
	 *
	 * @param int       $product_id Product id.
	 * @param Recipient $recipient Recipient.
	 * @return string
	 */
	public function edit_url( int $product_id, Recipient $recipient ): string {
		return user_can( $recipient->user_id, 'edit_post', $product_id ) ? get_admin_url( null, 'post.php?post=' . $product_id . '&action=edit' ) : (string) get_permalink( $product_id );
	}
	/**
	 * Restrict generated editing destinations to the configured site.
	 *
	 * @param mixed $url Url.
	 * @param int   $product_id Product id.
	 * @return string
	 */
	protected function safe_url( $url, int $product_id ): string {
		if ( ! is_string( $url ) || ! $url || wp_parse_url( $url, PHP_URL_HOST ) !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
			return (string) get_permalink( $product_id );
		}
		return esc_url_raw( $url, array( 'http', 'https' ) );
	}
}
