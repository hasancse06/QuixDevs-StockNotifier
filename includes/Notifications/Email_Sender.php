<?php
/**
 * Email Sender component.
 *
 * @package QuixDevsStockNotifier
 */

namespace QuixDevs\StockNotifier\Notifications;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use QuixDevs\StockNotifier\Support\Validator;

/** A true result is transport acceptance, never proof of inbox delivery. */
final class Email_Sender {
	/**
	 * Submit one private message and report mail transport acceptance.
	 *
	 * @param string $email Email.
	 * @param array  $rendered Rendered.
	 * @return bool
	 */
	public function send( string $email, array $rendered ): bool {
		if ( ! Validator::email( $email ) || preg_match( '/[\r\n\x00]/', $rendered['subject'] ) ) {
			return false;
		}
		$alternate = static function ( $mailer ) use ( $rendered ) {
			$mailer->AltBody = $rendered['plain'];
		};
		add_action( 'phpmailer_init', $alternate );
		try {
			return wp_mail( $email, $rendered['subject'], $rendered['html'], array( 'Content-Type: text/html; charset=UTF-8' ) );
		} finally {
			remove_action( 'phpmailer_init', $alternate );
		}
	}
}
