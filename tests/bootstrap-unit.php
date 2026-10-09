<?php
/** Minimal isolated unit dependencies. Integration tests use real WordPress. */
define( 'ABSPATH', sys_get_temp_dir() . '/' );
function __( $text, $domain = '' ) { return $text; }
function is_email( $email ) { return filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : false; }
require dirname( __DIR__ ) . '/includes/autoload.php';
