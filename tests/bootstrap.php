<?php
/**
 * PHPUnit bootstrap file for SilverAssist Settings Hub.
 *
 * @package SilverAssist\SettingsHub
 */

declare(strict_types=1);

// Composer autoloader for stubs and dependencies.
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Define test constants.
if ( ! defined( 'SILVERASSIST_SETTINGS_HUB_TESTING' ) ) {
	define( 'SILVERASSIST_SETTINGS_HUB_TESTING', true );
}
define( 'SILVERASSIST_TESTS_DIR', __DIR__ );
define( 'SILVERASSIST_PLUGIN_DIR', dirname( __DIR__ ) );

// WordPress test environment setup.
$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

// Determine WordPress Test Suite includes directory.
if ( file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	// Standard wordpress-tests-lib structure.
	$_tests_includes_dir = $_tests_dir . '/includes';
} elseif ( file_exists( $_tests_dir . '/tests/phpunit/includes/functions.php' ) ) {
	// wordpress-develop repository structure.
	$_tests_includes_dir = $_tests_dir . '/tests/phpunit/includes';
} else {
	echo "\n\033[1;31mError: WordPress Test Suite not found!\033[0m\n\n";
	echo "This package requires WordPress Test Suite for testing.\n";
	echo "Install it using the provided script:\n\n";
	echo "  \033[1;33mbash scripts/install-wp-tests.sh wordpress_test root 'root' localhost latest true\033[0m\n\n";
	echo "Or set WP_TESTS_DIR environment variable to your WordPress test suite location.\n\n";
	exit( 1 );
}

/**
 * Manually load the package being tested.
 *
 * @return void
 */
function _manually_load_package(): void {
	// Package is already loaded via Composer autoloader.
	// No additional loading needed for library packages.
}

// Load WordPress Test Suite.
require_once $_tests_includes_dir . '/functions.php';

tests_add_filter( 'muplugins_loaded', '_manually_load_package' );

// Start WordPress Test Suite.
require_once $_tests_includes_dir . '/bootstrap.php';

