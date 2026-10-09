<?php
/**
 * Settings component.
 *
 * @package QuixDevsStockNotifier
 */

namespace QuixDevs\StockNotifier\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/** Small, non-autoloaded configuration with independent monitoring generation. */
final class Settings {
	public const OPTION = 'quixdevs_stocknotifier_settings';
	/**
	 * Return the initial plugin configuration.
	 *
	 * @return array
	 */
	public static function defaults(): array {
		return array(
			'enabled'      => true,
			'threshold'    => 5,
			'out_of_stock' => true,
			'logging'      => true,
			'provider'     => 'wordpress',
			'authors'      => 'primary',
			'admin_copy'   => false,
			'admin_emails' => array(),
			'retry_limit'  => 3,
			'delete_data'  => false,
			'revision'     => 1,
			'generation'   => 1,
			'subject'      => 'Low Stock Alert: {PRODUCT_NAME} ({STOCK_QUANTITY} remaining)',
			'body'         => '<p>Hello {RECIPIENT_NAME},</p><p>Your product "{PRODUCT_NAME}" has reached the configured low-stock threshold.</p><p>Product: {PRODUCT_NAME}<br>SKU: {PRODUCT_SKU}<br>Current Stock: {STOCK_QUANTITY}<br>Low Stock Threshold: {STOCK_THRESHOLD}</p><p>Store: {STORE_NAME}</p><p>Please review your inventory and restock the product if necessary.</p><p>Manage Product:<br><a href="{PRODUCT_EDIT_URL}">{PRODUCT_EDIT_URL}</a></p><p>Thank you,<br>{SITE_NAME}</p>',
		);
	}
	/**
	 * Read validated configuration with safe defaults.
	 *
	 * @return array
	 */
	public static function get(): array {
		return array_merge( self::defaults(), (array) get_option( self::OPTION, array() ) );
	}
	/**
	 * Reload non-autoloaded policy after acquiring a cross-request lock.
	 *
	 * @return array
	 */
	public static function fresh(): array {
		wp_cache_delete( self::OPTION, 'options' );
		return self::get();
	}
	/**
	 * Validate the whole submitted configuration; failure leaves all state untouched.
	 *
	 * @param array                                                 $raw Raw.
	 * @param array                                                 $old Old.
	 * @param \QuixDevs\StockNotifier\Recipients\Recipient_Resolver $resolver Resolver.
	 * @return array
	 * @throws \InvalidArgumentException When validation or persistence cannot complete.
	 */
	public static function validate( array $raw, array $old, \QuixDevs\StockNotifier\Recipients\Recipient_Resolver $resolver ): array {
		$new              = $old;
		$new['threshold'] = Validator::threshold( $raw['threshold'] ?? null );
		$provider         = $raw['provider'] ?? '';
		if ( ! is_string( $provider ) || ! isset( $resolver->providers()[ $provider ] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception data is escaped at Admin::notice or private preview output.
			throw new \InvalidArgumentException( __( 'Select exactly one supported recipient provider.', 'quixdevs-stocknotifier' ) );
		}
		if ( $provider !== $old['provider'] && 'available' !== $resolver->providers()[ $provider ]->status() ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception data is escaped at Admin::notice or private preview output.
			throw new \InvalidArgumentException( __( 'The selected provider is unavailable. Configure its integration first.', 'quixdevs-stocknotifier' ) );
		}
		$new['provider'] = $provider;
		if ( ! in_array( $raw['authors'] ?? '', array( 'primary', 'all' ), true ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception data is escaped at Admin::notice or private preview output.
			throw new \InvalidArgumentException( __( 'Select a valid assigned-author policy.', 'quixdevs-stocknotifier' ) );
		}
		$new['authors'] = $raw['authors'];
		foreach ( array( 'enabled', 'out_of_stock', 'logging', 'admin_copy', 'delete_data' ) as $key ) {
			$new[ $key ] = isset( $raw[ $key ] ) && '1' === $raw[ $key ];
		}
		$new['admin_emails'] = Validator::emails( $raw['admin_emails'] ?? '' );
		if ( $new['admin_copy'] && ! $new['admin_emails'] ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception data is escaped at Admin::notice or private preview output.
			throw new \InvalidArgumentException( __( 'Specify at least one administrator copy address.', 'quixdevs-stocknotifier' ) );
		}
		$new['retry_limit'] = Validator::threshold( $raw['retry_limit'] ?? null );
		if ( $new['retry_limit'] > 5 ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception data is escaped at Admin::notice or private preview output.
			throw new \InvalidArgumentException( __( 'Automatic retries must be between zero and five.', 'quixdevs-stocknotifier' ) );
		}
		$policy = array( 'provider', 'authors', 'admin_copy', 'admin_emails', 'threshold', 'enabled', 'out_of_stock' );
		foreach ( $policy as $key ) {
			if ( $new[ $key ] !== $old[ $key ] ) {
				$new['revision'] = $old['revision'] + 1;
				break;
			}
		}
		foreach ( array( 'threshold', 'enabled', 'out_of_stock' ) as $key ) {
			if ( $new[ $key ] !== $old[ $key ] ) {
				$new['generation'] = $old['generation'] + 1;
				break;
			}
		}
		return $new;
	}
}
