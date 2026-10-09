<?php
/** Load only an explicitly supplied disposable WordPress installation. */
$root = getenv( 'QSN_WP_ROOT' );
if ( ! $root || ! is_readable( $root . '/wp-load.php' ) ) {
 throw new RuntimeException( 'Set QSN_WP_ROOT to a disposable WordPress test installation.' );
}
define( 'WP_USE_THEMES', false );
require $root . '/wp-load.php';
if ( ! str_contains( DB_NAME, 'qsn_test' ) || ! str_contains( DB_HOST, '/qsn-tests/' ) ) {
 throw new RuntimeException( 'Refusing to run against any database outside the isolated qsn-tests sandbox.' );
}
if ( ! class_exists( 'WooCommerce' ) || ! defined( 'QUIXDEVS_STOCKNOTIFIER_FILE' ) ) {
 throw new RuntimeException( 'Activate WooCommerce and StockNotifier in the test installation.' );
}
require __DIR__ . '/Integration/StockNotifierTestCase.php';
