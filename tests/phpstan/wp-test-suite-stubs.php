<?php
/**
 * Static analysis stubs for the WordPress test suite and runtime constants.
 *
 * PHPStan reads this file through `scanFiles` and never executes it. The real
 * WP_UnitTestCase and tests_add_filter() live in the WordPress test library,
 * which only exists once tests/bootstrap.php has located a checkout of it, so
 * the analysis cannot scan them. The signatures below mirror the parts of that
 * library the test suites use. ABSPATH and WP_PLUGIN_DIR are defined by
 * WordPress at runtime and are missing from the WordPress stubs.
 *
 * @package SilverAssist\SettingsHub\Tests
 */

declare(strict_types=1);

define( 'ABSPATH', '/var/www/html/' );
define( 'WP_PLUGIN_DIR', '/var/www/html/wp-content/plugins' );

/**
 * Add a filter before WordPress is loaded (test library function).
 *
 * @param string   $hook_name     Hook name.
 * @param callable $callback      Callback.
 * @param int      $priority      Priority.
 * @param int      $accepted_args Number of accepted arguments.
 *
 * @return true
 */
function tests_add_filter( string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
	return add_filter( $hook_name, $callback, $priority, $accepted_args );
}

/**
 * Factory for users, the only generator the suites use.
 */
class WP_UnitTest_Factory_For_User {
	/**
	 * Create a user.
	 *
	 * @param array<string, mixed> $args User fields.
	 *
	 * @return int User ID.
	 */
	public function create( array $args = array() ): int {
		return 0;
	}
}

/**
 * Factory exposed by WP_UnitTestCase::factory().
 */
class WP_UnitTest_Factory {
	/**
	 * User factory.
	 *
	 * @var WP_UnitTest_Factory_For_User
	 */
	public WP_UnitTest_Factory_For_User $user;
}

/**
 * Base class of every WordPress test suite test case.
 */
abstract class WP_UnitTestCase extends \PHPUnit\Framework\TestCase {
	/**
	 * Test data factory.
	 *
	 * @return WP_UnitTest_Factory
	 */
	protected static function factory(): WP_UnitTest_Factory {
		return new WP_UnitTest_Factory();
	}

	/**
	 * Declare that a _doing_it_wrong() notice is expected.
	 *
	 * @param string $doing_it_wrong Function name the notice names.
	 */
	public function setExpectedIncorrectUsage( string $doing_it_wrong ): void {
	}
}
