<?php
/**
 * Plugin component.
 *
 * @package QuixDevsStockNotifier
 */

namespace QuixDevs\StockNotifier;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use QuixDevs\StockNotifier\Data\Notification_Repository;
use QuixDevs\StockNotifier\Support\Logger;
use QuixDevs\StockNotifier\Recipients\Recipient_Resolver;
use QuixDevs\StockNotifier\Notifications\{Notification_Queue, Notification_Manager};
use QuixDevs\StockNotifier\Inventory\{Stock_Monitor, Reconciliation};
use QuixDevs\StockNotifier\Admin\Admin;

/** Composition root; zero inventory scans or email calls on ordinary page loads. */
final class Plugin {
	/**
	 * Compose services and register event-driven processing.
	 *
	 * @return void
	 */
	public static function boot(): void {
		if ( ! Dependencies::woocommerce() ) {
			add_action(
				'admin_notices',
				static function () {
					if ( current_user_can( 'manage_options' ) ) {
						echo '<div class="notice notice-error"><p>' . esc_html__( 'StockNotifier is paused: active WooCommerce 10.0 or newer is required.', 'quixdevs-stocknotifier' ) . '</p></div>';
					}
				}
			);
			return;
		}
		$repository     = new Notification_Repository();
		$resolver       = new Recipient_Resolver();
		$logger         = new Logger( $repository );
		$queue          = new Notification_Queue();
		$manager        = new Notification_Manager( $repository, $resolver, $queue, $logger );
		$monitor        = new Stock_Monitor( $repository, $manager, $logger );
		$reconciliation = new Reconciliation( $repository, $monitor, $manager );
		$monitor->hooks();
		add_action( Notification_Queue::HOOK, array( $manager, 'process' ), 10, 2 );
		add_action( 'quixdevs_stocknotifier_reconcile', array( $reconciliation, 'run' ) );
		add_action( 'quixdevs_stocknotifier_tool_batch', array( $reconciliation, 'tool_batch' ), 10, 2 );
		add_action(
			'init',
			static function () {
				if ( Dependencies::scheduler() && ! as_has_scheduled_action( 'quixdevs_stocknotifier_reconcile', array(), Notification_Queue::GROUP ) ) {
					as_schedule_recurring_action( time() + 30, MINUTE_IN_SECONDS, 'quixdevs_stocknotifier_reconcile', array(), Notification_Queue::GROUP, true );
				}
			},
			20
		);
		( new Admin( $repository, $resolver, $manager, $reconciliation, $logger ) )->hooks();
	}
}
