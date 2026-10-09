<?php
/**
 * Email Templates component.
 *
 * @package QuixDevsStockNotifier
 */

namespace QuixDevs\StockNotifier\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use QuixDevs\StockNotifier\Support\Settings;
use QuixDevs\StockNotifier\Notifications\Template_Renderer;

/** Native rich editor; synthetic preview and asynchronous test mail. */
final class Email_Templates {
	/**
	 * Render escaped native admin output or sanitized email content.
	 *
	 * @return void
	 */
	public function render(): void {
		$s = Settings::get();
		View::form( 'templates' );
		echo '<p><label for="qsn-subject"><strong>' . esc_html__( 'Email Subject', 'quixdevs-stocknotifier' ) . '</strong></label></p><input class="large-text" id="qsn-subject" name="subject" required maxlength="250" value="' . esc_attr( $s['subject'] ) . '">';
		echo '<p><label for="qsn-body"><strong>' . esc_html__( 'Email Body', 'quixdevs-stocknotifier' ) . '</strong></label></p>';
		wp_editor(
			$s['body'],
			'qsn-body',
			array(
				'textarea_name' => 'body',
				'media_buttons' => false,
				'textarea_rows' => 14,
			)
		);
		echo '<p><label for="qsn-test-email">' . esc_html__( 'Test destination', 'quixdevs-stocknotifier' ) . '</label> <input id="qsn-test-email" type="email" name="test_email" value="' . esc_attr( wp_get_current_user()->user_email ) . '"></p>';
		echo '<p><button class="button button-primary" name="template_action" value="save">' . esc_html__( 'Save Templates', 'quixdevs-stocknotifier' ) . '</button> <button class="button" name="template_action" value="preview">' . esc_html__( 'Preview Email', 'quixdevs-stocknotifier' ) . '</button> <button class="button" name="template_action" value="test">' . esc_html__( 'Send Test Email', 'quixdevs-stocknotifier' ) . '</button></p></form>';
		$test = get_option( 'quixdevs_stocknotifier_test_' . get_current_user_id() );
		if ( $test ) {
			echo '<p role="status">' . esc_html__( 'Latest TEST EMAIL status:', 'quixdevs-stocknotifier' ) . ' ' . esc_html( View::status( $test['status'] ) ) . '</p>';
		}
		echo '<h2>' . esc_html__( 'Available Variables', 'quixdevs-stocknotifier' ) . '</h2><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Variable', 'quixdevs-stocknotifier' ) . '</th><th>' . esc_html__( 'Sample value', 'quixdevs-stocknotifier' ) . '</th></tr></thead><tbody>';
		foreach ( ( new Template_Renderer() )->sample() as $key => $value ) {
			echo '<tr><td><code>{' . esc_html( $key ) . '}</code></td><td>' . esc_html( (string) $value ) . '</td></tr>';
		}
		echo '</tbody></table><p>' . esc_html__( 'Values are escaped. Unknown variables remain literal. PHP and shortcodes are never executed.', 'quixdevs-stocknotifier' ) . '</p>';
		$preview = get_transient( 'quixdevs_stocknotifier_preview_' . get_current_user_id() );
		if ( $preview ) {
			echo '<h2>' . esc_html__( 'Email Preview — Synthetic Sample', 'quixdevs-stocknotifier' ) . '</h2><p><strong>' . esc_html( $preview['subject'] ) . '</strong></p><div class="qsn-preview">' . wp_kses_post( $preview['html'] ) . '</div>';
		}
	}
}
