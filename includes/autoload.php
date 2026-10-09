<?php
/**
 * Bundled PSR-4 loader.
 *
 * @package QuixDevsStockNotifier
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
spl_autoload_register(
	static function ( $class_name ) {
		$prefix = 'QuixDevs\\StockNotifier\\';
		if ( str_starts_with( $class_name, $prefix ) ) {
			$file = __DIR__ . '/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
			if ( is_readable( $file ) ) {
				require_once $file;
			}
		}
	}
);
