<?php
/**
 * Recipient Resolver component.
 *
 * @package QuixDevsStockNotifier
 */

namespace QuixDevs\StockNotifier\Recipients;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use QuixDevs\StockNotifier\Support\Settings;

/** One explicit provider; errors carry a reason and never trigger a fallback. */
final class Recipient_Resolver {
	/**
	 * Return the isolated supported recipient providers.
	 *
	 * @return array
	 */
	public function providers(): array {
		return array(
			'wordpress'    => new WordPress_Author(),
			'dokan'        => new Dokan_Vendor(),
			'wcvendors'    => new WCVendors_Vendor(),
			'publishpress' => new PublishPress_Authors(),
		);
	}
	/**
	 * Resolve eligible recipients from the explicit provider policy.
	 *
	 * @param int        $product_id Product id.
	 * @param array|null $settings Settings.
	 * @return array
	 */
	public function resolve( int $product_id, ?array $settings = null ): array {
		$settings = $settings ?? Settings::get();
		$provider = $this->providers()[ $settings['provider'] ] ?? null;
		if ( ! $provider || 'available' !== $provider->status() ) {
			return array(
				'recipients' => array(),
				'reason'     => __( 'Selected integration is unavailable or requires configuration. Notifications are paused.', 'quixdevs-stocknotifier' ),
				'available'  => false,
			);
		}
		$product = wc_get_product( $product_id );
		if ( ! $product || 'trash' === $product->get_status() ) {
			return array(
				'recipients' => array(),
				'reason'     => __( 'Product no longer exists.', 'quixdevs-stocknotifier' ),
				'available'  => true,
			);
		}
		$product_id = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product_id;
		try {
			$recipients = $provider->resolve( $product_id, $settings['authors'] );
			$unique     = array();
			foreach ( $recipients as $recipient ) {
				$unique[ $recipient->key() ] = $recipient;
			}
			return array(
				'recipients' => array_values( $unique ),
				'reason'     => __( 'Resolved from the selected provider and verified WordPress accounts.', 'quixdevs-stocknotifier' ),
				'available'  => true,
			);
		} catch ( \Throwable $error ) {
			return array(
				'recipients' => array(),
				'reason'     => $error->getMessage(),
				'available'  => true,
			);
		}
	}
	/**
	 * Resolve a destination the recipient can access.
	 *
	 * @param Recipient $recipient Recipient.
	 * @return string
	 */
	public function edit_url( Recipient $recipient ): string {
		return $this->providers()[ $recipient->provider ]->edit_url( $recipient->product_id, $recipient );
	}
}
