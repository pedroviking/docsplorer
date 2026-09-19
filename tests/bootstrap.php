<?php
/**
 * Test bootstrap.
 *
 * This suite does NOT boot a real WordPress install or database -- it
 * uses WP_Mock to fake just the WordPress functions each test needs,
 * so the tests run in plain PHP and stay fast. That means we're
 * testing our own logic in isolation, not a full WordPress request;
 * see tests/README.md for what this suite does and doesn't cover.
 */

$autoload = dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! file_exists( $autoload ) ) {
	fwrite( STDERR, "\nDependencies aren't installed yet. Run `composer install` in the plugin folder first.\n\n" );
	exit( 1 );
}

require_once $autoload;

WP_Mock::bootstrap();

// The plugin's include files exit immediately if ABSPATH isn't defined
// (their normal protection against being loaded outside WordPress).
// We just need *some* value here so that guard doesn't trigger.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/docsplorer-tests/' );
}

/**
 * Each include file calls a handful of WordPress functions directly at
 * the top level (add_action, add_shortcode, register_post_type, ...) so
 * that WordPress wires them up when the file loads for real. We don't
 * want to assert anything about those particular calls in this suite,
 * we just need them to be harmless no-ops so the files can load and
 * hand us their functions to test. Individual tests are free to
 * override any of these with a real WP_Mock::userFunction() expectation
 * if a specific test does care about one.
 */
if ( ! function_exists( 'add_action' ) ) {
	function add_action( ...$args ) {
		return true;
	}
}
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( ...$args ) {
		return true;
	}
}
if ( ! function_exists( 'add_shortcode' ) ) {
	function add_shortcode( ...$args ) {
		return true;
	}
}
if ( ! function_exists( 'register_post_type' ) ) {
	function register_post_type( ...$args ) {
		return true;
	}
}
if ( ! function_exists( 'register_taxonomy' ) ) {
	function register_taxonomy( ...$args ) {
		return true;
	}
}
if ( ! function_exists( 'add_meta_box' ) ) {
	function add_meta_box( ...$args ) {
		return true;
	}
}

require_once dirname( __DIR__ ) . '/includes/helpers.php';
require_once dirname( __DIR__ ) . '/includes/shortcode.php';
