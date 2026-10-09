<?php
/**
 * Admin component.
 *
 * @package QuixDevsStockNotifier
 */

namespace QuixDevs\StockNotifier\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use QuixDevs\StockNotifier\Data\Notification_Repository;
use QuixDevs\StockNotifier\Recipients\Recipient_Resolver;
use QuixDevs\StockNotifier\Notifications\{Notification_Manager, Notification_Queue, Template_Renderer, Email_Sender};
use QuixDevs\StockNotifier\Inventory\Reconciliation;
use QuixDevs\StockNotifier\Support\{Settings, Validator, Lock_Manager, Logger};
use QuixDevs\StockNotifier\Dependencies;

/** All entry points enforce administrator access, nonce checks and POST semantics. */
final class Admin {
	/**
	 * Connect the collaborating services for this component.
	 *
	 * @param Notification_Repository $repository Repository.
	 * @param Recipient_Resolver      $resolver Resolver.
	 * @param Notification_Manager    $manager Manager.
	 * @param Reconciliation          $reconciliation Reconciliation.
	 * @param Logger                  $logger Logger.
	 */
	public function __construct( private Notification_Repository $repository, private Recipient_Resolver $resolver, private Notification_Manager $manager, private Reconciliation $reconciliation, private Logger $logger ) {}
	/**
	 * Register this component with the WordPress lifecycle.
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_quixdevs_stocknotifier_admin', array( $this, 'handle' ) );
		add_action( 'wp_ajax_quixdevs_stocknotifier_products', array( $this, 'products' ) );
		add_action( 'admin_notices', array( $this, 'notice' ) );
		add_action( 'quixdevs_stocknotifier_test_email', array( $this, 'test_email' ), 10, 2 );
	}
	/**
	 * Require the administrator capability at every private entry point.
	 *
	 * @return void
	 */
	public function authorize(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Administrator permission required.', 'quixdevs-stocknotifier' ), '', array( 'response' => 403 ) );
		}
	}
	/**
	 * Register the dedicated top-level StockNotifier admin screens.
	 *
	 * @return void
	 */
	public function menu(): void {
		add_menu_page( __( 'StockNotifier', 'quixdevs-stocknotifier' ), __( 'StockNotifier', 'quixdevs-stocknotifier' ), 'manage_options', 'quixdevs-stocknotifier', array( $this, 'screen' ), 'dashicons-email-alt', 56 );
		foreach ( $this->screens() as $slug => $label ) {
			add_submenu_page( 'quixdevs-stocknotifier', $label, $label, 'manage_options', $slug, array( $this, 'screen' ) );
		}
	}
	/**
	 * Return the allowlisted admin screen labels.
	 *
	 * @return array
	 */
	private function screens(): array {
		return array(
			'quixdevs-stocknotifier'           => __( 'Dashboard', 'quixdevs-stocknotifier' ),
			'quixdevs-stocknotifier-products'  => __( 'Low Stock Products', 'quixdevs-stocknotifier' ),
			'quixdevs-stocknotifier-templates' => __( 'Email Templates', 'quixdevs-stocknotifier' ),
			'quixdevs-stocknotifier-settings'  => __( 'Settings', 'quixdevs-stocknotifier' ),
			'quixdevs-stocknotifier-activity'  => __( 'Activity', 'quixdevs-stocknotifier' ),
			'quixdevs-stocknotifier-tools'     => __( 'Tools', 'quixdevs-stocknotifier' ),
		);
	}
	/**
	 * Enqueue lightweight enhancements only on StockNotifier screens.
	 *
	 * @param string $hook Hook.
	 * @return void
	 */
	public function assets( string $hook ): void {
		if ( ! str_contains( $hook, 'quixdevs-stocknotifier' ) ) {
			return;
		}
		wp_enqueue_style( 'quixdevs-stocknotifier-admin', plugins_url( 'assets/css/admin.css', QUIXDEVS_STOCKNOTIFIER_FILE ), array(), '1.0.0' );
		wp_enqueue_script( 'quixdevs-stocknotifier-admin', plugins_url( 'assets/js/admin.js', QUIXDEVS_STOCKNOTIFIER_FILE ), array(), '1.0.0', true );
		wp_localize_script(
			'quixdevs-stocknotifier-admin',
			'quixdevsStockNotifier',
			array(
				'url'       => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'quixdevs_stocknotifier_products' ),
				'searching' => __( 'Searching…', 'quixdevs-stocknotifier' ),
				'empty'     => __( 'No matching products.', 'quixdevs-stocknotifier' ),
				'error'     => __( 'Search failed. Please try again.', 'quixdevs-stocknotifier' ),
			)
		);
	}
	/**
	 * Dispatch an authorized read-only admin screen.
	 *
	 * @return void
	 */
	public function screen(): void {
		$this->authorize();
		$page = sanitize_key( $_GET['page'] ?? 'quixdevs-stocknotifier' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing allowlist.
		echo '<div class="wrap qsn"><h1>StockNotifier — ' . esc_html( $this->screens()[ $page ] ?? '' ) . '</h1>';
		switch ( $page ) {
			case 'quixdevs-stocknotifier-settings':
				( new Settings_Page() )->render( $this->resolver );
				break;
			case 'quixdevs-stocknotifier-templates':
				( new Email_Templates() )->render();
				break;
			case 'quixdevs-stocknotifier-products':
				( new Low_Stock_List() )->render( $this->repository, $this->resolver );
				break;
			case 'quixdevs-stocknotifier-activity':
				( new Activity_Page() )->render( $this->repository );
				break;
			case 'quixdevs-stocknotifier-tools':
				( new Tools_Page() )->render();
				break;
			default:
				( new Dashboard() )->render( $this->repository );
		}
		echo '</div>';
	}
	/**
	 * Display escaped diagnostic notices to administrators.
	 *
	 * @return void
	 */
	public function notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s = Settings::get();
		if ( 'available' !== $this->resolver->providers()[ $s['provider'] ]->status() ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'StockNotifier: the selected recipient provider is unavailable or needs configuration. Its selection is preserved and affected notifications are paused.', 'quixdevs-stocknotifier' ) . '</p></div>';
		}
		if ( ! Dependencies::scheduler() ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'StockNotifier requires initialized Action Scheduler. Pending notifications remain in durable storage.', 'quixdevs-stocknotifier' ) . '</p></div>';
		}
		$message = get_transient( 'quixdevs_stocknotifier_notice_' . get_current_user_id() );
		if ( $message ) {
			delete_transient( 'quixdevs_stocknotifier_notice_' . get_current_user_id() );
			echo '<div class="notice ' . esc_attr( $message['error'] ? 'notice-error' : 'notice-success' ) . ' is-dismissible"><p>' . esc_html( $message['text'] ) . '</p></div>';
		}
	}
	/**
	 * Validate a nonce-protected POST action and dispatch its operation.
	 *
	 * @return void
	 * @throws \InvalidArgumentException When validation or persistence cannot complete.
	 * @throws \RuntimeException When background scheduling is unavailable.
	 */
	public function handle(): void {
		$this->authorize();
		if ( 'POST' !== sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
			wp_die( esc_html__( 'POST required.', 'quixdevs-stocknotifier' ), '', array( 'response' => 405 ) );
		}
		$task = sanitize_key( $_POST['task'] ?? '' );
		check_admin_referer( 'quixdevs_stocknotifier_' . $task );
		$page = 'tools';
		try {
			$raw = wp_unslash( $_POST );
			if ( 'settings' === $task ) {
				$page = 'settings';
				Lock_Manager::run(
					'configuration',
					function () use ( $raw ) {
						$old = Settings::fresh();
						$new = Settings::validate( $raw, $old, $this->resolver );
						update_option( Settings::OPTION, $new, false );
						if ( $new['revision'] !== $old['revision'] ) {
							$this->repository->cancel_obsolete( $new['revision'] );
							$this->logger->log(
								'configuration_changed',
								0,
								0,
								array(
									'provider' => $new['provider'],
									'revision' => $new['revision'],
								)
							);
						}
						if ( $new['generation'] !== $old['generation'] ) {
							update_option( 'quixdevs_stocknotifier_scan_cursor', 0, false );
							update_option( 'quixdevs_stocknotifier_next_scan', 0, false );
						}
					}
				);
			} elseif ( 'templates' === $task ) {
				$page = 'templates';
				$this->templates( $raw );
			} elseif ( 'recipient' === $task ) {
				$id      = absint( $raw['product_id'] ?? 0 );
				$product = wc_get_product( $id );
				if ( ! $product ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception data is escaped at Admin::notice or private preview output.
					throw new \InvalidArgumentException( __( 'Select a valid WooCommerce product.', 'quixdevs-stocknotifier' ) );
				}
				$result               = $this->resolver->resolve( $id );
				$result['product']    = $product->get_name();
				$result['provider']   = Settings::get()['provider'];
				$result['recipients'] = array_map(
					static fn( $r ) => array(
						'name'  => $r->name,
						'email' => $r->email,
					),
					$result['recipients']
				);
				set_transient( 'quixdevs_stocknotifier_recipient_' . get_current_user_id(), $result, 300 );
			} else {
				if ( ! Dependencies::scheduler() ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception data is escaped at Admin::notice or private preview output.
					throw new \RuntimeException( __( 'Action Scheduler is unavailable.', 'quixdevs-stocknotifier' ) );
				}
				if ( 'preview_existing' !== $task && '1' !== ( $raw['confirm'] ?? '' ) ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception data is escaped at Admin::notice or private preview output.
					throw new \InvalidArgumentException( __( 'Explicit confirmation is required.', 'quixdevs-stocknotifier' ) );
				}
				switch ( $task ) {
					case 'preview_existing':
						$this->reconciliation->start_tool( 'preview', get_current_user_id() );
						break;
					case 'notify_existing':
						$this->reconciliation->confirm_existing( sanitize_text_field( $raw['run_id'] ?? '' ), get_current_user_id() );
						break;
					case 'baseline':
						$this->reconciliation->start_tool( 'baseline', get_current_user_id() );
						break;
					case 'retry_all':
						$this->reconciliation->start_tool( 'retry', get_current_user_id() );
						break;
					case 'repair':
						as_enqueue_async_action( 'quixdevs_stocknotifier_reconcile', array(), Notification_Queue::GROUP );
						break;
					case 'retry':
						$page = 'activity';
						$this->manager->retry( absint( $raw['delivery_id'] ?? 0 ) );
						break;
					default:
						// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception data is escaped at Admin::notice or private preview output.
						throw new \InvalidArgumentException( __( 'Unknown action.', 'quixdevs-stocknotifier' ) );
				}
			}
			$notice = array(
				'error' => false,
				'text'  => __( 'Request completed or queued. Background results appear on this screen.', 'quixdevs-stocknotifier' ),
			);
		} catch ( \Throwable $error ) {
			$notice = array(
				'error' => true,
				'text'  => $error->getMessage(),
			);
		}
		set_transient( 'quixdevs_stocknotifier_notice_' . get_current_user_id(), $notice, 120 );
		wp_safe_redirect( admin_url( 'admin.php?page=quixdevs-stocknotifier-' . $page ) );
		exit;
	}
	/**
	 * Sanitize templates or queue a synthetic administrator test message.
	 *
	 * @param array $raw Raw.
	 * @return void
	 * @throws \InvalidArgumentException When validation or persistence cannot complete.
	 */
	private function templates( array $raw ): void {
		$subject = $raw['subject'] ?? '';
		$body    = $raw['body'] ?? '';
		if ( ! is_string( $subject ) || ! is_string( $body ) || '' === trim( $subject ) || strlen( $subject ) > 250 || strlen( $body ) > 100000 || preg_match( '/[\r\n\x00]/', $subject ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception data is escaped at Admin::notice or private preview output.
			throw new \InvalidArgumentException( __( 'Enter a valid subject without line breaks and a body under 100 KB.', 'quixdevs-stocknotifier' ) );
		}
		$s            = Settings::get();
		$s['subject'] = sanitize_text_field( $subject );
		$s['body']    = wp_kses_post( $body );
		$action       = $raw['template_action'] ?? '';
		if ( 'save' === $action ) {
			Lock_Manager::run(
				'configuration',
				static function () use ( $s ) {
					$current            = Settings::get();
					$current['subject'] = $s['subject'];
					$current['body']    = $s['body'];
					update_option( Settings::OPTION, $current, false );
				}
			);
			return;
		}
		$renderer = new Template_Renderer();
		$rendered = $renderer->render( $renderer->sample(), $s );
		if ( 'preview' === $action ) {
			set_transient( 'quixdevs_stocknotifier_preview_' . get_current_user_id(), $rendered, 300 );
		} elseif ( 'test' === $action ) {
			if ( ! Validator::email( $raw['test_email'] ?? null ) || ! Dependencies::scheduler() ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception data is escaped at Admin::notice or private preview output.
				throw new \InvalidArgumentException( __( 'A valid test destination and Action Scheduler are required.', 'quixdevs-stocknotifier' ) );
			}
			Lock_Manager::run(
				'test:' . get_current_user_id(),
				static function () use ( $raw, $rendered ) {
					$key      = 'quixdevs_stocknotifier_test_' . get_current_user_id();
					$previous = get_option( $key );
					if ( $previous && time() - $previous['created'] < 60 ) {
						// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception data is escaped at Admin::notice or private preview output.
						throw new \RuntimeException( __( 'Wait one minute between test emails.', 'quixdevs-stocknotifier' ) );
					}
					$rendered['subject'] = '[TEST EMAIL] ' . $rendered['subject'];
					$rendered['html']    = '<p><strong>TEST EMAIL — Synthetic sample</strong></p>' . $rendered['html'];
					$rendered['plain']   = "TEST EMAIL — Synthetic sample\n" . $rendered['plain'];
					$job                 = array(
						'token'    => wp_generate_uuid4(),
						'email'    => $raw['test_email'],
						'rendered' => $rendered,
						'status'   => 'queued',
						'created'  => time(),
					);
					update_option( $key, $job, false );
					as_enqueue_async_action(
						'quixdevs_stocknotifier_test_email',
						array(
							'user_id' => get_current_user_id(),
							'token'   => $job['token'],
						),
						Notification_Queue::GROUP,
						true
					);
				}
			);
		} else {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception data is escaped at Admin::notice or private preview output.
			throw new \InvalidArgumentException( __( 'Unknown template action.', 'quixdevs-stocknotifier' ) );
		}
	}
	/**
	 * Process one administrator-authorized synthetic test email.
	 *
	 * @param int    $user_id User id.
	 * @param string $token Token.
	 * @return void
	 */
	public function test_email( int $user_id, string $token ): void {
		Lock_Manager::run(
			'test:' . $user_id,
			static function () use ( $user_id, $token ) {
				$key = 'quixdevs_stocknotifier_test_' . $user_id;
				$job = get_option( $key );
				if ( ! $job || ! hash_equals( $job['token'], $token ) || 'queued' !== $job['status'] || ! user_can( $user_id, 'manage_options' ) || time() - $job['created'] > HOUR_IN_SECONDS ) {
					return;
				}
				$job['status'] = 'unknown';
				update_option( $key, $job, false );
				try {
					$job['status'] = ( new Email_Sender() )->send( $job['email'], $job['rendered'] ) ? 'accepted' : 'failed';
				} catch ( \Throwable $error ) {
					$job['status'] = 'unknown';
				}
				unset( $job['email'], $job['rendered'] );
				update_option( $key, $job, false );
			}
		);
	}
	/**
	 * Return a bounded, private product-search response.
	 *
	 * @return void
	 */
	public function products(): void {
		$this->authorize();
		check_ajax_referer( 'quixdevs_stocknotifier_products', 'nonce' );
		$term    = sanitize_text_field( wp_unslash( $_GET['term'] ?? '' ) );
		$ids     = \WC_Data_Store::load( 'product' )->search_products( $term, '', true, false, 20 );
		$results = array();
		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			if ( $product && 'trash' !== $product->get_status() ) {
				$results[] = array(
					'id'   => $id,
					'name' => $product->get_name() . ' (#' . $id . ')',
				);
			}
		}
		wp_send_json_success( $results );
	}
}
