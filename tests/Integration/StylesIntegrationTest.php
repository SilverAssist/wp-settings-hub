<?php
/**
 * Integration tests for the Settings Hub stylesheet enqueueing.
 *
 * @package SilverAssist\SettingsHub\Tests\Integration
 */

declare(strict_types=1);

namespace SilverAssist\SettingsHub\Tests\Integration;

use SilverAssist\SettingsHub\SettingsHub;
use SilverAssist\SettingsHub\Tests\TestCase;

/**
 * Covers enqueue_styles() and the asset URL resolution behind it.
 *
 * Every test registers the hub against a fake plugin tree built by
 * make_vendor_fixture() and inspects the style WordPress actually registered.
 */
class StylesIntegrationTest extends TestCase {
	/**
	 * Style handle the hub registers.
	 *
	 * @var string
	 */
	private const HANDLE = 'silverassist-settings-hub';

	/**
	 * Hook suffix of the hub's top-level page.
	 *
	 * @var string
	 */
	private const HUB_SUFFIX = 'toplevel_page_silver-assist';

	/**
	 * The admin screen that was current before the test, restored in tearDown().
	 *
	 * @var mixed
	 */
	private $previous_screen = null;

	/**
	 * Whether a screen was set before the test.
	 *
	 * @var bool
	 */
	private bool $had_screen = false;

	/**
	 * Give core's admin_enqueue_scripts callbacks the screen they expect in wp-admin.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->had_screen      = array_key_exists( 'current_screen', $GLOBALS );
		$this->previous_screen = $GLOBALS['current_screen'] ?? null;
		set_current_screen( 'dashboard' );
	}

	/**
	 * Restore the screen that was current before the test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		if ( $this->had_screen ) {
			$GLOBALS['current_screen'] = $this->previous_screen;
		} else {
			unset( $GLOBALS['current_screen'] );
		}

		parent::tearDown();
	}

	/**
	 * Fetch the style object WordPress registered for the hub handle.
	 *
	 * @return \_WP_Dependency
	 */
	private function registered_style(): \_WP_Dependency {
		$style = wp_styles()->registered[ self::HANDLE ] ?? null;
		if ( ! $style instanceof \_WP_Dependency ) {
			throw new \RuntimeException( 'The hub style is not registered.' );
		}

		return $style;
	}

	/**
	 * Register a throwaway plugin with the hub.
	 *
	 * @param string               $slug Plugin slug.
	 * @param array<string,string> $args Registration args, for example plugin_file.
	 *
	 * @return void
	 */
	private function register( string $slug, array $args = array() ): void {
		$this->hub()->register_plugin( $slug, 'Plugin ' . $slug, '__return_true', $args );
	}

	/**
	 * Read the hub's private PACKAGE_VERSION constant.
	 *
	 * @return string
	 */
	private function package_version(): string {
		$constant = new \ReflectionClassConstant( SettingsHub::class, 'PACKAGE_VERSION' );

		return (string) $constant->getValue();
	}

	/**
	 * Fire admin_enqueue_scripts for a hook suffix.
	 *
	 * @param string $hook_suffix Admin page hook suffix.
	 *
	 * @return void
	 */
	private function enqueue_for( string $hook_suffix ): void {
		do_action( 'admin_enqueue_scripts', $hook_suffix );
	}

	/**
	 * Throw away the style queue so a test can try another hook suffix.
	 *
	 * @return void
	 */
	private function reset_styles(): void {
		$GLOBALS['wp_styles'] = new \WP_Styles();
	}

	/**
	 * Count how many times the hub's enqueue_styles callback is hooked.
	 *
	 * @return int
	 */
	private function count_enqueue_hooks(): int {
		$hook = $GLOBALS['wp_filter']['admin_enqueue_scripts'] ?? null;
		if ( ! $hook instanceof \WP_Hook ) {
			return 0;
		}

		$count = 0;
		foreach ( $hook->callbacks as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$function = $callback['function'];
				if ( is_array( $function ) && $function[0] === $this->hub() && 'enqueue_styles' === $function[1] ) {
					++$count;
				}
			}
		}

		return $count;
	}

	/**
	 * Create a plain plugin file in a temp dir that has no vendor package.
	 *
	 * @return array{file: string, dir: string}
	 */
	private function make_plain_plugin(): array {
		$dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/settings-hub-plain-' . uniqid( '', true );
		mkdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
		$file = $dir . '/plain.php';
		file_put_contents( $file, "<?php\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		return array(
			'file' => $file,
			'dir'  => $dir,
		);
	}

	/**
	 * Remove a directory made by make_plain_plugin().
	 *
	 * @param array{file: string, dir: string} $plain The plain plugin.
	 *
	 * @return void
	 */
	private function remove_plain_plugin( array $plain ): void {
		if ( file_exists( $plain['file'] ) ) {
			unlink( $plain['file'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		}
		if ( is_dir( $plain['dir'] ) ) {
			rmdir( $plain['dir'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		}
	}

	/**
	 * Enqueueing on the hub page registers the handle with the vendor URL and package version.
	 *
	 * @return void
	 */
	public function test_enqueue_registers_handle_with_vendor_src_and_package_version(): void {
		$fixture = $this->make_vendor_fixture();
		$this->register( 'my-plugin', array( 'plugin_file' => $fixture['file'] ) );

		$this->assertFalse( wp_style_is( self::HANDLE, 'enqueued' ), 'Nothing is enqueued before the hook fires.' );

		$this->enqueue_for( self::HUB_SUFFIX );

		$this->assertTrue( wp_style_is( self::HANDLE, 'enqueued' ) );
		$style = $this->registered_style();
		$this->assertSame( $fixture['css_url'], $style->src );
		$this->assertSame( $this->package_version(), $style->ver );
		$this->assertSame( array(), $style->deps );
	}

	/**
	 * Registering plugins hooks enqueue_styles once and yields a single enqueued handle.
	 *
	 * @return void
	 */
	public function test_register_plugin_wires_enqueue_styles_on_admin_enqueue_scripts(): void {
		$fixture  = $this->make_vendor_fixture();
		$callback = array( $this->hub(), 'enqueue_styles' );

		$this->assertFalse( has_action( 'admin_enqueue_scripts', $callback ), 'Not hooked before any registration.' );

		$this->register( 'plugin-one', array( 'plugin_file' => $fixture['file'] ) );
		$this->assertNotFalse( has_action( 'admin_enqueue_scripts', $callback ) );

		$this->register( 'plugin-two' );
		$this->register( 'plugin-three' );

		$this->assertSame( 1, $this->count_enqueue_hooks() );

		$this->enqueue_for( self::HUB_SUFFIX );

		$this->assertSame( 1, count( array_keys( wp_styles()->queue, self::HANDLE, true ) ) );
		$this->assertTrue( wp_style_is( self::HANDLE, 'enqueued' ) );
	}

	/**
	 * The styles hook is added on the first registration only, never re-added afterwards.
	 *
	 * @return void
	 */
	public function test_styles_hook_is_added_only_on_the_first_registration(): void {
		$callback = array( $this->hub(), 'enqueue_styles' );

		$this->register( 'plugin-one' );
		$this->assertNotFalse( has_action( 'admin_enqueue_scripts', $callback ) );

		// If a later registration re-added the hook, it would reappear after this removal.
		remove_action( 'admin_enqueue_scripts', $callback );
		$this->register( 'plugin-two' );

		$this->assertFalse( has_action( 'admin_enqueue_scripts', $callback ) );
	}

	/**
	 * Styles load on the hub's own hook suffixes and not on core admin screens.
	 *
	 * The guard in enqueue_styles() is currently a substring check on "silver-assist",
	 * so it also matches unrelated suffixes such as "settings_page_silver-assist-security".
	 * Phase 4 makes the match exact. This test deliberately does not assert that
	 * false-positive behaviour either way.
	 *
	 * @return void
	 */
	public function test_styles_load_on_hub_hook_suffixes_only(): void {
		$fixture = $this->make_vendor_fixture();
		$this->register( 'my-plugin', array( 'plugin_file' => $fixture['file'] ) );

		foreach ( array( self::HUB_SUFFIX, 'silver-assist_page_my-plugin' ) as $suffix ) {
			$this->reset_styles();
			$this->enqueue_for( $suffix );
			$this->assertTrue( wp_style_is( self::HANDLE, 'enqueued' ), "Expected the stylesheet on {$suffix}." );
		}

		foreach ( array( 'edit.php', 'index.php', 'plugins.php', 'options-general.php' ) as $suffix ) {
			$this->reset_styles();
			$this->enqueue_for( $suffix );
			$this->assertFalse( wp_style_is( self::HANDLE, 'enqueued' ), "Unexpected stylesheet on {$suffix}." );
			$this->assertFalse( wp_style_is( self::HANDLE, 'registered' ), "Unexpected registration on {$suffix}." );
		}
	}

	/**
	 * The primary branch builds the URL from the plugin file and tolerates a leading slash.
	 *
	 * @return void
	 */
	public function test_asset_url_primary_branch_resolves_from_plugin_file(): void {
		$fixture = $this->make_vendor_fixture();
		$this->register( 'my-plugin', array( 'plugin_file' => $fixture['file'] ) );

		$this->assertSame(
			$fixture['css_url'],
			$this->call_hub_method( 'get_package_asset_url', array( 'assets/css/settings-hub.css' ) )
		);
		$this->assertSame(
			$fixture['css_url'],
			$this->call_hub_method( 'get_package_asset_url', array( '/assets/css/settings-hub.css' ) )
		);
	}

	/**
	 * A vendor directory without the CSS file falls through to the fallback, which fails here.
	 *
	 * The fallback resolves from the package's own directory (this worktree), which is not
	 * under ABSPATH during a test run, so the URL stays empty.
	 *
	 * @return void
	 */
	public function test_asset_url_falls_through_when_vendor_dir_exists_but_css_is_missing(): void {
		$fixture = $this->make_vendor_fixture( 'settings-hub-no-css', false );
		$this->assertDirectoryExists( $fixture['dir'] . '/vendor/silverassist/wp-settings-hub' );
		$this->assertFileDoesNotExist( $fixture['dir'] . '/vendor/silverassist/wp-settings-hub/assets/css/settings-hub.css' );

		$this->register( 'my-plugin', array( 'plugin_file' => $fixture['file'] ) );

		$this->assertSame( '', $this->call_hub_method( 'get_package_asset_url', array( 'assets/css/settings-hub.css' ) ) );

		$this->enqueue_for( self::HUB_SUFFIX );

		$this->assertFalse( wp_style_is( self::HANDLE, 'enqueued' ) );
		$this->assertFalse( wp_style_is( self::HANDLE, 'registered' ) );
	}

	/**
	 * A plugin file with no vendor package next to it resolves to nothing.
	 *
	 * @return void
	 */
	public function test_asset_url_is_empty_when_plugin_file_has_no_vendor_package(): void {
		$plain = $this->make_plain_plugin();

		try {
			$this->register( 'my-plugin', array( 'plugin_file' => $plain['file'] ) );

			$this->assertSame( '', $this->call_hub_method( 'get_package_asset_url', array( 'assets/css/settings-hub.css' ) ) );

			$this->enqueue_for( self::HUB_SUFFIX );

			$this->assertFalse( wp_style_is( self::HANDLE, 'enqueued' ) );
			$this->assertFalse( wp_style_is( self::HANDLE, 'registered' ) );
		} finally {
			$this->remove_plain_plugin( $plain );
		}
	}

	/**
	 * Without a plugin file only the fallback runs, and it cannot resolve outside ABSPATH.
	 *
	 * The fallback can only succeed when the package lives under ABSPATH, which a test run
	 * cannot arrange (the repository is not inside the WordPress install). Its success path
	 * is therefore not covered here.
	 *
	 * @return void
	 */
	public function test_asset_url_is_empty_without_plugin_file_when_package_is_outside_abspath(): void {
		$this->register( 'my-plugin' );

		$this->assertSame( '', $this->call_hub_method( 'get_package_asset_url', array( 'assets/css/settings-hub.css' ) ) );

		$this->enqueue_for( self::HUB_SUFFIX );

		$this->assertFalse( wp_style_is( self::HANDLE, 'enqueued' ) );
	}

	/**
	 * Only the first plugin file is used for asset resolution.
	 *
	 * @return void
	 */
	public function test_only_first_plugin_file_is_used_for_asset_resolution(): void {
		$fixture_a = $this->make_vendor_fixture( 'settings-hub-fixture-a' );
		$fixture_b = $this->make_vendor_fixture( 'settings-hub-fixture-b' );
		$this->assertNotSame( $fixture_a['css_url'], $fixture_b['css_url'] );

		$this->register( 'plugin-a', array( 'plugin_file' => $fixture_a['file'] ) );
		$this->register( 'plugin-b', array( 'plugin_file' => $fixture_b['file'] ) );

		$this->enqueue_for( self::HUB_SUFFIX );

		$style = $this->registered_style();
		$this->assertSame( $fixture_a['css_url'], $style->src );
	}

	/**
	 * The first NON-EMPTY plugin file wins: earlier registrations without one do not count.
	 *
	 * Pins the current behaviour of register_plugin(): a missing or empty plugin_file
	 * leaves the hub's plugin file unset, so the next registration that supplies one
	 * sets it, and every registration after that is ignored.
	 *
	 * @return void
	 */
	public function test_first_non_empty_plugin_file_wins_when_earlier_registrations_have_none(): void {
		$fixture_b = $this->make_vendor_fixture( 'settings-hub-fixture-b' );
		$fixture_c = $this->make_vendor_fixture( 'settings-hub-fixture-c' );

		$this->register( 'plugin-a' );
		$this->register( 'plugin-empty', array( 'plugin_file' => '' ) );
		$this->register( 'plugin-b', array( 'plugin_file' => $fixture_b['file'] ) );
		$this->register( 'plugin-c', array( 'plugin_file' => $fixture_c['file'] ) );

		$this->enqueue_for( self::HUB_SUFFIX );

		$style = $this->registered_style();
		$this->assertSame( $fixture_b['css_url'], $style->src );
	}
}
