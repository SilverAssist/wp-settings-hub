<?php
/**
 * WordPress-driven tests for when plugins may register and which pages get the stylesheet.
 *
 * @package SilverAssist\SettingsHub\Tests\Integration
 */

declare(strict_types=1);

namespace SilverAssist\SettingsHub\Tests\Integration;

use SilverAssist\SettingsHub\Tests\TestCase;

/**
 * Covers the registration window (before, during and after admin_menu), the
 * idempotence of the menu registration, and the exact hook suffixes that load
 * the stylesheet. Everything is read from $submenu and from the enqueue queue
 * after real admin_menu and admin_enqueue_scripts actions.
 */
class RegistrationTimingIntegrationTest extends TestCase {
	/**
	 * Function name WordPress must be told about when a plugin registers too late.
	 *
	 * @var string
	 */
	private const REGISTER = 'SilverAssist\SettingsHub\SettingsHub::register_plugin';

	/**
	 * Start every test as an administrator, so add_submenu_page() keeps its rows.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->login_as( 'administrator' );
	}

	/**
	 * Register a throwaway plugin with the hub.
	 *
	 * @param string               $slug Plugin slug.
	 * @param array<string,string> $args Registration args.
	 *
	 * @return void
	 */
	private function register( string $slug, array $args = array() ): void {
		$this->hub()->register_plugin( $slug, 'Plugin ' . $slug, '__return_true', $args );
	}

	/**
	 * Slugs WordPress holds under the hub's menu, in order.
	 *
	 * @return string[]
	 */
	private function submenu_slugs(): array {
		$rows = (array) ( $GLOBALS['submenu']['silver-assist'] ?? array() );

		return array_values( array_map( static fn( $row ): string => (string) $row[2], $rows ) );
	}

	/**
	 * Whether the hub stylesheet is enqueued after admin_enqueue_scripts fires for a hook suffix.
	 *
	 * @param string $hook_suffix Admin page hook suffix.
	 *
	 * @return bool
	 */
	private function styles_load_on( string $hook_suffix ): bool {
		$GLOBALS['wp_styles'] = new \WP_Styles();
		do_action( 'admin_enqueue_scripts', $hook_suffix );

		return wp_style_is( 'silverassist-settings-hub', 'enqueued' );
	}

	/**
	 * Firing admin_menu twice leaves each menu row once.
	 *
	 * @return void
	 */
	public function test_firing_admin_menu_twice_registers_each_page_once(): void {
		$this->register( 'early' );
		$this->register( 'second' );
		$this->fire_admin_menu();
		$this->fire_admin_menu();

		$this->assertSame( array( 'silver-assist', 'early', 'second' ), $this->submenu_slugs() );
	}

	/**
	 * Registering the same slug again does not add a second row.
	 *
	 * @return void
	 */
	public function test_registering_a_slug_twice_before_admin_menu_registers_one_page(): void {
		$this->register( 'same' );
		$this->register( 'same' );
		$this->fire_admin_menu();

		$this->assertSame( array( 'silver-assist', 'same' ), $this->submenu_slugs() );
	}

	/**
	 * A plugin that registers from an admin_menu callback after the hub's priority still gets its page.
	 *
	 * @return void
	 */
	public function test_plugin_registered_during_admin_menu_after_priority_five_gets_a_submenu(): void {
		$this->register( 'early' );
		add_action(
			'admin_menu',
			function (): void {
				$this->register( 'late' );
			},
			10
		);
		$this->fire_admin_menu();

		$this->assertSame( array( 'silver-assist', 'early', 'late' ), $this->submenu_slugs() );
		$this->assertTrue( $this->can_access_page( 'late' ) );
	}

	/**
	 * A plugin that registers before the hub's hook runs is still registered exactly once.
	 *
	 * @return void
	 */
	public function test_plugin_registered_during_admin_menu_before_priority_five_is_registered_once(): void {
		$this->register( 'first' );
		add_action(
			'admin_menu',
			function (): void {
				$this->register( 'second' );
			},
			1
		);
		$this->fire_admin_menu();

		$this->assertSame( array( 'silver-assist', 'first', 'second' ), $this->submenu_slugs() );
	}

	/**
	 * Registering after admin_menu has finished is reported, and the plugin is still kept.
	 *
	 * @return void
	 */
	public function test_registering_after_admin_menu_is_flagged(): void {
		$this->register( 'early' );
		$this->fire_admin_menu();

		$this->setExpectedIncorrectUsage( self::REGISTER );
		$this->register( 'too-late' );

		$this->assertTrue( $this->hub()->is_plugin_registered( 'too-late' ) );
	}

	/**
	 * Registering on or before admin_menu raises no notice.
	 *
	 * @return void
	 */
	public function test_registering_inside_the_window_raises_no_notice(): void {
		$this->register( 'before' );
		add_action(
			'admin_menu',
			function (): void {
				$this->register( 'during' );
			},
			10
		);
		$this->fire_admin_menu();

		// WP_UnitTestCase fails the test on an unexpected _doing_it_wrong(); reaching here is the assertion.
		$this->assertCount( 3, $this->submenu_slugs() );
	}

	/**
	 * Styles load on the hub pages and on no look-alike hook suffix.
	 *
	 * @return void
	 */
	public function test_styles_load_on_hub_hook_suffixes_and_not_on_look_alikes(): void {
		$fixture = $this->make_vendor_fixture();
		$this->register( 'my-plugin', array( 'plugin_file' => $fixture['file'] ) );
		$this->fire_admin_menu();

		foreach ( array( 'toplevel_page_silver-assist', 'silver-assist_page_my-plugin' ) as $suffix ) {
			$this->assertTrue( $this->styles_load_on( $suffix ), "Expected the stylesheet on {$suffix}." );
		}

		$look_alikes = array(
			'settings_page_silver-assist-security',
			'toplevel_page_not-silver-assist-at-all',
			'silver-assist-security_page_general',
			'my-silver-assist_page_x',
			'edit.php',
		);
		foreach ( $look_alikes as $suffix ) {
			$this->assertFalse( $this->styles_load_on( $suffix ), "Unexpected stylesheet on {$suffix}." );
		}
	}

	/**
	 * The hook suffix follows the translated parent title, as core builds it.
	 *
	 * @return void
	 */
	public function test_styles_follow_the_hook_suffix_core_builds_for_a_translated_parent_title(): void {
		add_filter(
			'gettext',
			static function ( $translation, $text, $domain ) {
				return ( 'Silver Assist' === $text && 'silverassist-settings-hub' === $domain ) ? 'Plata Asistencia' : $translation;
			},
			10,
			3
		);
		$fixture = $this->make_vendor_fixture();
		$this->register( 'my-plugin', array( 'plugin_file' => $fixture['file'] ) );
		$this->fire_admin_menu();

		$core_suffix = get_plugin_page_hookname( 'my-plugin', 'silver-assist' );
		$this->assertSame( 'plata-asistencia_page_my-plugin', $core_suffix, 'Sanity check of what core derives' );

		$this->assertTrue( $this->styles_load_on( $core_suffix ), 'The stylesheet must load on the suffix core generated.' );
		$this->assertTrue( $this->styles_load_on( 'toplevel_page_silver-assist' ) );
		$this->assertFalse( $this->styles_load_on( 'silver-assist_page_my-plugin' ), 'The untranslated suffix does not exist on this site.' );
	}
}
