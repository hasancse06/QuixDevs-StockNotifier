<?php
/**
 * Template Renderer component.
 *
 * @package QuixDevsStockNotifier
 */

namespace QuixDevs\StockNotifier\Notifications;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use QuixDevs\StockNotifier\Recipients\{Recipient, Recipient_Resolver};
use QuixDevs\StockNotifier\Support\Settings;

/** Literal replacement only; no shortcodes or executable template language. */
final class Template_Renderer {
	public const VARIABLES = array( 'PRODUCT_NAME', 'PRODUCT_ID', 'PRODUCT_SKU', 'PRODUCT_URL', 'PRODUCT_EDIT_URL', 'STOCK_QUANTITY', 'STOCK_THRESHOLD', 'RECIPIENT_NAME', 'AUTHOR_NAME', 'VENDOR_NAME', 'VENDOR_STORE_NAME', 'SITE_NAME', 'SITE_URL', 'STORE_NAME', 'ALERT_DATE', 'ALERT_TIME', 'PRODUCT_TYPE', 'VARIATION_NAME' );
	/**
	 * Build literal template variables from verified inventory and recipient data.
	 *
	 * @param mixed              $product Product.
	 * @param Recipient          $recipient Recipient.
	 * @param int                $threshold Threshold.
	 * @param Recipient_Resolver $resolver Resolver.
	 * @return array
	 */
	public function values( $product, Recipient $recipient, int $threshold, Recipient_Resolver $resolver ): array {
		$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		return array(
			'PRODUCT_NAME'      => $product->get_name(),
			'PRODUCT_ID'        => $product->get_id(),
			'PRODUCT_SKU'       => $product->get_sku() ? $product->get_sku() : __( 'Not set', 'quixdevs-stocknotifier' ),
			'PRODUCT_URL'       => $product->get_permalink(),
			'PRODUCT_EDIT_URL'  => $resolver->edit_url( $recipient ),
			'STOCK_QUANTITY'    => $product->get_stock_quantity(),
			'STOCK_THRESHOLD'   => $threshold,
			'RECIPIENT_NAME'    => $recipient->name,
			'AUTHOR_NAME'       => $recipient->name,
			'VENDOR_NAME'       => $recipient->name,
			'VENDOR_STORE_NAME' => $recipient->store_name ? $recipient->store_name : $site,
			'SITE_NAME'         => $site,
			'SITE_URL'          => home_url( '/' ),
			'STORE_NAME'        => $recipient->store_name ? $recipient->store_name : $site,
			'ALERT_DATE'        => wp_date( get_option( 'date_format' ) ),
			'ALERT_TIME'        => wp_date( get_option( 'time_format' ) ),
			'PRODUCT_TYPE'      => $product->get_type(),
			'VARIATION_NAME'    => $product->is_type( 'variation' ) ? wc_get_formatted_variation( $product, true, true, false ) : '',
		);
	}
	/**
	 * Return realistic synthetic values without accessing recipient accounts.
	 *
	 * @return array
	 */
	public function sample(): array {
		return array_combine( self::VARIABLES, array( 'Wireless Headphones', '123', 'HEADPHONES-BLUE', home_url( '/product/sample/' ), home_url( '/product/sample/' ), '5', '5', 'Sample Author', 'Sample Author', 'Sample Vendor', 'Sample Store', get_bloginfo( 'name' ), home_url( '/' ), 'Sample Store', wp_date( 'Y-m-d' ), wp_date( 'H:i' ), 'variation', 'Color: Blue' ) );
	}
	/**
	 * Render escaped native admin output or sanitized email content.
	 *
	 * @param array      $values Values.
	 * @param array|null $settings Settings.
	 * @return array
	 */
	public function render( array $values, ?array $settings = null ): array {
		$settings = $settings ?? Settings::get();
		$subject  = array();
		$body     = array();
		foreach ( self::VARIABLES as $variable ) {
			$value                            = (string) ( $values[ $variable ] ?? '' );
			$subject[ '{' . $variable . '}' ] = preg_replace( '/[\r\n\x00]/', '', wp_strip_all_tags( $value ) );
			$body[ '{' . $variable . '}' ]    = str_ends_with( $variable, '_URL' ) ? esc_url( $value ) : esc_html( $value );
		}
		$title = preg_replace( '/[\r\n\x00]/', '', wp_strip_all_tags( strtr( $settings['subject'], $subject ) ) );
		$html  = wpautop( wp_kses_post( strtr( $settings['body'], $body ) ) );
		// Unknown variables remain visible as literal text, never executed.
		$wrapped = '<div style="background:#f5f5f5;padding:24px"><div style="max-width:600px;margin:auto;background:#fff;padding:28px;font-family:Arial,sans-serif;color:#333;line-height:1.6">' . $html . '</div></div>';
		return array(
			'subject' => $title,
			'html'    => $wrapped,
			'plain'   => html_entity_decode( wp_strip_all_tags( str_replace( array( '</p>', '<br>', '<br />' ), "\n", $html ) ), ENT_QUOTES, 'UTF-8' ),
		);
	}
}
