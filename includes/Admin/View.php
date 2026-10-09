<?php
/**
 * View component.
 *
 * @package QuixDevsStockNotifier
 */

namespace QuixDevs\StockNotifier\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/** Small native admin markup helpers. All screens inherit the capability gate. */
final class View {
	/**
	 * Open a native POST form with a task-specific nonce.
	 *
	 * @param string $task Task.
	 * @return void
	 */
	public static function form( string $task ): void {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="quixdevs_stocknotifier_admin"><input type="hidden" name="task" value="' . esc_attr( $task ) . '">';
		wp_nonce_field( 'quixdevs_stocknotifier_' . $task );
	}
	/**
	 * Close a form with an accessible native submit button.
	 *
	 * @param string $text Text.
	 * @param bool   $primary Primary.
	 * @return void
	 */
	public static function button( string $text, bool $primary = false ): void {
		submit_button( $text, $primary ? 'primary' : 'secondary' );
		echo '</form>';
	}
	/**
	 * Output one native settings row from pre-escaped controls.
	 *
	 * @param string $label Label.
	 * @param string $html Html.
	 * @param string $id Id.
	 * @return void
	 */
	public static function row( string $label, string $html, string $id = '' ): void {
		echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></th><td>' . $html . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-escaped form controls from internal callers.
	}
	/**
	 * Output a native checkbox setting.
	 *
	 * @param string $key Key.
	 * @param string $label Label.
	 * @param bool   $value Value.
	 * @return void
	 */
	public static function toggle( string $key, string $label, bool $value ): void {
		self::row( $label, '<input id="qsn-' . esc_attr( $key ) . '" type="checkbox" name="' . esc_attr( $key ) . '" value="1" ' . checked( $value, true, false ) . '>', 'qsn-' . $key );
	}
	/**
	 * Output a strict integer field with accessible validation feedback.
	 *
	 * @param string $key Key.
	 * @param string $label Label.
	 * @param int    $value Value.
	 * @param int    $max Max.
	 * @return void
	 */
	public static function quantity( string $key, string $label, int $value, int $max ): void {
		$id = 'qsn-' . $key;
		self::row( $label, '<input class="qsn-integer" id="' . esc_attr( $id ) . '" name="' . esc_attr( $key ) . '" type="number" min="0" max="' . esc_attr( (string) $max ) . '" step="1" inputmode="numeric" required value="' . esc_attr( (string) $value ) . '" aria-describedby="' . esc_attr( $id ) . '-error"><p class="qsn-error" id="' . esc_attr( $id ) . '-error" aria-live="polite" data-message="' . esc_attr__( 'Use digits only, within the displayed range. No signs, spaces or decimals.', 'quixdevs-stocknotifier' ) . '"></p>', $id );
	}
	/**
	 * Output pagination controls for bounded results.
	 *
	 * @param int $total Total.
	 * @param int $page Page.
	 * @return void
	 */
	public static function pagination( int $total, int $page ): void {
		echo '<div class="tablenav"><div class="tablenav-pages">';
		echo wp_kses_post(
			paginate_links(
				array(
					'base'    => add_query_arg( 'paged', '%#%' ),
					'format'  => '',
					'current' => $page,
					'total'   => max( 1, (int) ceil( $total / 25 ) ),
				)
			)
		);
		echo '</div></div>';
	}
	/**
	 * Report integration API and configuration availability.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	public static function status( string $status ): string {
		$labels = array(
			'available'     => __( 'Available', 'quixdevs-stocknotifier' ),
			'not_installed' => __( 'Not Installed', 'quixdevs-stocknotifier' ),
			'inactive'      => __( 'Inactive', 'quixdevs-stocknotifier' ),
			'unsupported'   => __( 'Unsupported API / Configuration Required', 'quixdevs-stocknotifier' ),
			'accepted'      => __( 'Accepted by mail transport', 'quixdevs-stocknotifier' ),
			'unknown'       => __( 'Delivery unknown — review before sending again', 'quixdevs-stocknotifier' ),
			'healthy'       => __( 'Healthy', 'quixdevs-stocknotifier' ),
			'low'           => __( 'Low stock / baseline', 'quixdevs-stocknotifier' ),
			'queued'        => __( 'Queued', 'quixdevs-stocknotifier' ),
			'sending'       => __( 'Attempted / Sending', 'quixdevs-stocknotifier' ),
			'failed'        => __( 'Failed', 'quixdevs-stocknotifier' ),
			'paused'        => __( 'Paused', 'quixdevs-stocknotifier' ),
			'unresolved'    => __( 'Unresolved recipient', 'quixdevs-stocknotifier' ),
			'cancelled'     => __( 'Cancelled', 'quixdevs-stocknotifier' ),
			'disabled'      => __( 'Disabled', 'quixdevs-stocknotifier' ),
		);
		return $labels[ $status ] ?? $status;
	}
}
