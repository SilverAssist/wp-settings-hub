<?php
/**
 * Base TestCase for SilverAssist Settings Hub.
 *
 * @package SilverAssist\SettingsHub\Tests
 */

declare(strict_types=1);

namespace SilverAssist\SettingsHub\Tests;

use SilverAssist\SettingsHub\SettingsHub;

/**
 * Base test case for Settings Hub tests.
 *
 * Extends WordPress Test Suite's WP_UnitTestCase to provide access to real
 * WordPress functions, database operations, and factory methods. On top of
 * that it gives every test a clean admin-menu state (the globals that
 * add_menu_page() and add_submenu_page() write to are never reset by
 * WP_UnitTestCase), a fresh style queue and a fresh Settings Hub singleton,
 * and offers the helpers the suites share.
 */
abstract class TestCase extends \WP_UnitTestCase {
	/**
	 * Globals holding the admin menu tree, reset to an empty array for each test.
	 *
	 * @var string[]
	 */
	private const ARRAY_GLOBALS = array(
		'menu',
		'submenu',
		'_wp_submenu_nopriv',
		'_wp_menu_nopriv',
		'_wp_real_parent_file',
		'_registered_pages',
		'_parent_pages',
		'admin_page_hooks',
	);

	/**
	 * Globals describing the current admin page, restored after each test.
	 *
	 * @var string[]
	 */
	private const PAGE_GLOBALS = array( 'pagenow', 'plugin_page', 'typenow', 'parent_file', 'submenu_file', 'hook_suffix', 'wp_styles', 'current_screen' );

	/**
	 * Snapshot of the globals above, keyed by name.
	 *
	 * @var array<string, array{exists: bool, value: mixed}>
	 */
	private array $global_snapshot = array();

	/**
	 * Snapshot of $_GET['page'].
	 *
	 * @var array{exists: bool, value: mixed}
	 */
	private array $get_page_snapshot = array(
		'exists' => false,
		'value'  => null,
	);

	/**
	 * Directories created by make_vendor_fixture(), removed in tearDown().
	 *
	 * @var string[]
	 */
	private array $fixture_dirs = array();

	/**
	 * Start every test from a clean hub, menu tree and style queue.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		foreach ( array_merge( self::ARRAY_GLOBALS, self::PAGE_GLOBALS ) as $name ) {
			$this->global_snapshot[ $name ] = array(
				'exists' => array_key_exists( $name, $GLOBALS ),
				'value'  => $GLOBALS[ $name ] ?? null,
			);
		}

		$this->get_page_snapshot = array(
			'exists' => array_key_exists( 'page', $_GET ),
			'value'  => $_GET['page'] ?? null,
		);

		foreach ( self::ARRAY_GLOBALS as $name ) {
			$GLOBALS[ $name ] = array();
		}

		$GLOBALS['wp_styles'] = new \WP_Styles();

		// wp-admin always has a current screen; core callbacks on admin hooks expect one.
		set_current_screen( 'dashboard' );

		$this->reset_hub();
	}

	/**
	 * Restore the globals, drop the fixtures and discard the hub singleton.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$this->reset_hub();

		foreach ( $this->global_snapshot as $name => $snapshot ) {
			if ( $snapshot['exists'] ) {
				$GLOBALS[ $name ] = $snapshot['value'];
			} else {
				unset( $GLOBALS[ $name ] );
			}
		}

		if ( $this->get_page_snapshot['exists'] ) {
			$_GET['page'] = $this->get_page_snapshot['value'];
		} else {
			unset( $_GET['page'] );
		}

		foreach ( $this->fixture_dirs as $dir ) {
			$this->remove_directory( $dir );
		}
		$this->fixture_dirs = array();

		parent::tearDown();
	}

	/**
	 * Discard the singleton so the next get_instance() builds a fresh hub.
	 *
	 * @return void
	 */
	protected function reset_hub(): void {
		$instance = new \ReflectionProperty( SettingsHub::class, 'instance' );
		$instance->setValue( null, null );
	}

	/**
	 * Get the (fresh) hub.
	 *
	 * @return SettingsHub
	 */
	protected function hub(): SettingsHub {
		return SettingsHub::get_instance();
	}

	/**
	 * Create a user with the given role and make it the current user.
	 *
	 * @param string $role WordPress role, for example administrator, editor or subscriber.
	 *
	 * @return int The user ID.
	 */
	protected function login_as( string $role ): int {
		$user_id = static::factory()->user->create( array( 'role' => $role ) );
		wp_set_current_user( $user_id );

		return $user_id;
	}

	/**
	 * Fire the admin_menu action the way wp-admin does.
	 *
	 * @return void
	 */
	protected function fire_admin_menu(): void {
		do_action( 'admin_menu' );
	}

	/**
	 * Run a callable and return everything it printed.
	 *
	 * @param callable $callback Code that echoes output.
	 *
	 * @return string The captured output.
	 */
	protected function render( callable $callback ): string {
		ob_start();
		try {
			$callback();
		} finally {
			$output = (string) ob_get_clean();
		}

		return $output;
	}

	/**
	 * Render a hub page through the callback WordPress attached to it.
	 *
	 * This goes through add_submenu_page()'s own hook, so it only produces
	 * output when the current user passed the page's capability check.
	 *
	 * @param string $slug Page slug: a plugin slug, or the hub's parent slug for the Dashboard.
	 *
	 * @return string The rendered page, empty when WordPress attached no callback.
	 */
	protected function render_registered_page( string $slug ): string {
		$hook = get_plugin_page_hookname( $slug, $this->hub()->get_parent_slug() );

		return $this->render(
			static function () use ( $hook ): void {
				do_action( $hook );
			}
		);
	}

	/**
	 * Ask WordPress whether the current user may open an admin page.
	 *
	 * Mirrors what wp-admin/menu.php does before it renders a page: it sets the
	 * request globals and calls user_can_access_admin_page().
	 *
	 * @param string $page The page slug (the ?page= value).
	 *
	 * @return bool
	 */
	protected function can_access_page( string $page ): bool {
		unset( $GLOBALS['parent_file'] );

		$GLOBALS['pagenow']     = 'admin.php';
		$GLOBALS['plugin_page'] = $page;
		$_GET['page']           = $page;

		return (bool) user_can_access_admin_page();
	}

	/**
	 * Create a temporary plugin tree that contains the hub's stylesheet.
	 *
	 * The tree mimics vendor/silverassist/wp-settings-hub under WP_PLUGIN_DIR so
	 * get_package_asset_url() can resolve it from the returned plugin file.
	 * It is removed in tearDown(), even when the test fails.
	 *
	 * @param string $name        Directory name of the fake plugin.
	 * @param bool   $with_asset  Whether to create the CSS file (false leaves the vendor directory empty).
	 *
	 * @return array{file: string, dir: string, css_url: string} Paths and the URL the stylesheet should get.
	 */
	protected function make_vendor_fixture( string $name = 'settings-hub-fixture', bool $with_asset = true ): array {
		$dir        = WP_PLUGIN_DIR . '/' . $name;
		$package    = $dir . '/vendor/silverassist/wp-settings-hub';
		$css_dir    = $package . '/assets/css';
		$plugin_php = $dir . '/' . $name . '.php';

		wp_mkdir_p( $with_asset ? $css_dir : $package );
		file_put_contents( $plugin_php, "<?php\n/* Plugin Name: Settings Hub fixture */\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( $with_asset ) {
			file_put_contents( $css_dir . '/settings-hub.css', '/* fixture */' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}

		$this->fixture_dirs[] = $dir;

		return array(
			'file'    => $plugin_php,
			'dir'     => $dir,
			'css_url' => rtrim( plugin_dir_url( $plugin_php ), '/' ) . '/vendor/silverassist/wp-settings-hub/assets/css/settings-hub.css',
		);
	}

	/**
	 * Call a private or protected method on the hub.
	 *
	 * @param string       $method Method name.
	 * @param array<mixed> $args   Arguments.
	 *
	 * @return mixed The method's return value.
	 */
	protected function call_hub_method( string $method, array $args = array() ): mixed {
		$reflection = new \ReflectionMethod( SettingsHub::class, $method );

		return $reflection->invokeArgs( $this->hub(), $args );
	}

	/**
	 * Recursively delete a directory.
	 *
	 * @param string $dir Directory to remove.
	 *
	 * @return void
	 */
	private function remove_directory( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$items = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $items as $item ) {
			if ( $item->isDir() ) {
				rmdir( $item->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
			} else {
				unlink( $item->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			}
		}
		rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
	}
}
