<?php
/**
 * Settings Page component.
 *
 * @package QuixDevsStockNotifier
 */

namespace QuixDevs\StockNotifier\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use QuixDevs\StockNotifier\Support\{Settings, Validator};
use QuixDevs\StockNotifier\Recipients\Recipient_Resolver;

/** Native controls with visible, explicit recipient policy. */
final class Settings_Page {
	/**
	 * Render escaped native admin output or sanitized email content.
	 *
	 * @param Recipient_Resolver $resolver Resolver.
	 * @return void
	 */
	public function render( Recipient_Resolver $resolver ): void {
		$s = Settings::get();
		View::form( 'settings' );
		echo '<h2>' . esc_html__( 'General', 'quixdevs-stocknotifier' ) . '</h2><table class="form-table"><tbody>';
		View::toggle( 'enabled', __( 'Enable StockNotifier', 'quixdevs-stocknotifier' ), $s['enabled'] );
		View::quantity( 'threshold', __( 'Global Low Stock Threshold', 'quixdevs-stocknotifier' ), $s['threshold'], Validator::MAX_THRESHOLD );
		View::toggle( 'out_of_stock', __( 'Enable Out-of-Stock Alerts', 'quixdevs-stocknotifier' ), $s['out_of_stock'] );
		View::toggle( 'logging', __( 'Activity Logging', 'quixdevs-stocknotifier' ), $s['logging'] );
		echo '</tbody></table><p>' . esc_html__( 'Disabling out-of-stock alerts suppresses crossings at zero or negative stock. Threshold changes establish new baselines without a campaign.', 'quixdevs-stocknotifier' ) . '</p>';
		echo '<h2>' . esc_html__( 'Notification Recipients', 'quixdevs-stocknotifier' ) . '</h2><fieldset><legend><strong>' . esc_html__( 'Notification Recipient Source', 'quixdevs-stocknotifier' ) . '</strong></legend>';
		foreach ( $resolver->providers() as $id => $provider ) {
			$status      = $provider->status();
			$unavailable = 'available' !== $status && $s['provider'] !== $id;
			echo '<p><label><input type="radio" name="provider" value="' . esc_attr( $id ) . '" ' . checked( $s['provider'], $id, false ) . ' ' . disabled( $unavailable, true, false ) . '> ' . esc_html( $provider->label() ) . '</label> <span class="qsn-status">' . esc_html( View::status( $status ) ) . '</span></p>';
		}
		echo '</fieldset><fieldset id="qsn-authors"><legend><strong>' . esc_html__( 'Notify Assigned Authors', 'quixdevs-stocknotifier' ) . '</strong></legend>';
		foreach ( array(
			'primary' => __( 'Primary Assigned Author', 'quixdevs-stocknotifier' ),
			'all'     => __( 'All Assigned Registered Authors', 'quixdevs-stocknotifier' ),
		) as $id => $label ) {
			echo '<p><label><input type="radio" name="authors" value="' . esc_attr( $id ) . '" ' . checked( $s['authors'], $id, false ) . '> ' . esc_html( $label ) . '</label></p>';
		}
		echo '<p>' . esc_html__( 'Guest authors without a linked WordPress account are skipped. Primary means the first author in PublishPress assignment order.', 'quixdevs-stocknotifier' ) . '</p></fieldset>';
		echo '<h2>' . esc_html__( 'Optional Admin Copy', 'quixdevs-stocknotifier' ) . '</h2><table class="form-table"><tbody>';
		View::toggle( 'admin_copy', __( 'Send Additional Copy to Administrator', 'quixdevs-stocknotifier' ), $s['admin_copy'] );
		View::row( __( 'Additional Administrator Emails', 'quixdevs-stocknotifier' ), '<input class="regular-text" id="qsn-admin-emails" type="email" multiple name="admin_emails" value="' . esc_attr( implode( ', ', $s['admin_emails'] ) ) . '"><p class="description">' . esc_html__( 'Comma-separated, maximum ten addresses. Each copy is sent individually.', 'quixdevs-stocknotifier' ) . '</p>', 'qsn-admin-emails' );
		echo '</tbody></table><h2>' . esc_html__( 'Advanced', 'quixdevs-stocknotifier' ) . '</h2><table class="form-table"><tbody>';
		View::quantity( 'retry_limit', __( 'Automatic retries after a rejected send', 'quixdevs-stocknotifier' ), $s['retry_limit'], 5 );
		View::toggle( 'delete_data', __( 'Delete Plugin Data on Uninstall', 'quixdevs-stocknotifier' ), $s['delete_data'] );
		echo '</tbody></table><p>' . esc_html__( 'Default uninstall behavior preserves data. Delivery-unknown sends are never retried automatically. History retention: 90 days.', 'quixdevs-stocknotifier' ) . '</p>';
		View::button( __( 'Save Settings', 'quixdevs-stocknotifier' ), true );
	}
}
