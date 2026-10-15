<?php
/**
 * Tests for the per-plugin capability of the Settings Hub.
 *
 * @package SilverAssist\SettingsHub\Tests
 */

declare(strict_types=1);

namespace SilverAssist\SettingsHub\Tests\Unit;

use SilverAssist\SettingsHub\Tests\TestCase;

/**
 * Unit-tier tests (they boot WordPress, like the rest of the suite) for the
 * capability argument of register_plugin(), its filters and what the hub
 * renders for users who do or do not hold it. Rendering is called directly;
 * the WordPress-driven checks live in CapabilityIntegrationTest.
 *
 * @covers \SilverAssist\SettingsHub\SettingsHub
 */
final class CapabilityTest extends TestCase {
	/**
	 * Function name WordPress must be told about when an invalid capability is passed.
	 *
	 * @var string
	 */
	private const NORMALIZE = 'SilverAssist\SettingsHub\SettingsHub::normalize_capability';

	/**
	 * Start every test as an administrator; tests switch roles with login_as().
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->login_as( 'administrator' );
	}

	/**
	 * Register a plugin whose settings page prints a marker.
	 *
	 * @param string               $slug Plugin slug.
	 * @param array<string, mixed> $args Extra registration arguments.
	 *
	 * @return void
	 */
	private function register( string $slug, array $args = array() ): void {
		$this->hub()->register_plugin(
			$slug,
			'Plugin ' . $slug,
			static function () use ( $slug ): void {
				echo 'page-of-' . esc_html( $slug );
			},
			$args
		);
	}

	/**
	 * Get the stored data for a plugin.
	 *
	 * @param string $slug Plugin slug.
	 *
	 * @return array<string, mixed>
	 */
	private function stored( string $slug ): array {
		return $this->hub()->get_plugins()[ $slug ];
	}

	/**
	 * Render the dashboard as the current user.
	 *
	 * @return string
	 */
	private function dashboard(): string {
		return $this->render( array( $this->hub(), 'render_dashboard' ) );
	}

	/**
	 * Render the tabs navigation as the current user.
	 *
	 * @param string $active_slug Active plugin slug.
	 *
	 * @return string
	 */
	private function tabs( string $active_slug ): string {
		return $this->render(
			function () use ( $active_slug ): void {
				$this->call_hub_method( 'render_tabs_navigation', array( $active_slug ) );
			}
		);
	}

	/**
	 * A plugin that does not pass a capability keeps requiring manage_options.
	 *
	 * @return void
	 */
	public function test_capability_defaults_to_manage_options(): void {
		$this->register( 'plain' );

		$this->assertSame( 'manage_options', $this->stored( 'plain' )['capability'] );
	}

	/**
	 * A custom capability is stored as given.
	 *
	 * @return void
	 */
	public function test_custom_capability_is_stored(): void {
		$this->register( 'editors', array( 'capability' => 'edit_posts' ) );

		$this->assertSame( 'edit_posts', $this->stored( 'editors' )['capability'] );
	}

	/**
	 * Whitespace around the capability is trimmed.
	 *
	 * @return void
	 */
	public function test_capability_is_trimmed(): void {
		$this->register( 'editors', array( 'capability' => "  edit_posts \n" ) );

		$this->assertSame( 'edit_posts', $this->stored( 'editors' )['capability'] );
	}

	/**
	 * Custom capabilities are case sensitive, so the value must not be lowercased.
	 *
	 * @return void
	 */
	public function test_mixed_case_capability_is_preserved(): void {
		$this->register( 'custom', array( 'capability' => 'Manage_Foo-Bar9' ) );

		$this->assertSame( 'Manage_Foo-Bar9', $this->stored( 'custom' )['capability'] );
	}

	/**
	 * A null capability behaves like no capability and is not reported.
	 *
	 * @return void
	 */
	public function test_null_capability_falls_back_silently(): void {
		$this->register( 'nothing', array( 'capability' => null ) );

		$this->assertSame( 'manage_options', $this->stored( 'nothing' )['capability'] );
	}

	/**
	 * Provide capability values the hub must reject.
	 *
	 * @return array<string, array{0: mixed}>
	 */
	public function provide_invalid_capabilities(): array {
		return array(
			'empty string'        => array( '' ),
			'only whitespace'     => array( '   ' ),
			'integer'             => array( 123 ),
			'boolean'             => array( true ),
			'array'               => array( array( 'edit_posts' ) ),
			'space inside'        => array( 'bad cap' ),
			'punctuation'         => array( 'bad cap!' ),
			'semicolon'           => array( 'edit_posts;manage_options' ),
			'meta capability arg' => array( 'edit_post:5' ),
		);
	}

	/**
	 * An invalid capability fails closed to manage_options and is flagged to the developer.
	 *
	 * @dataProvider provide_invalid_capabilities
	 *
	 * @param mixed $value The rejected value.
	 *
	 * @return void
	 */
	public function test_invalid_capability_falls_back_and_is_flagged( $value ): void {
		$this->setExpectedIncorrectUsage( self::NORMALIZE );

		$this->register( 'broken', array( 'capability' => $value ) );

		$this->assertSame( 'manage_options', $this->stored( 'broken' )['capability'] );
	}

	/**
	 * The per-plugin filter replaces the stored capability and receives the plugin context.
	 *
	 * @return void
	 */
	public function test_plugin_capability_filter_overrides_the_stored_value(): void {
		$this->register( 'filtered', array( 'capability' => 'edit_posts' ) );

		$seen = array();
		add_filter(
			'silverassist_settings_hub_plugin_capability',
			static function ( $capability, $slug, $plugin ) use ( &$seen ) {
				$seen = array( $capability, $slug, $plugin['name'] );

				return 'read';
			},
			10,
			3
		);

		$this->assertSame( 'read', $this->call_hub_method( 'get_plugin_capability', array( $this->stored( 'filtered' ) ) ) );
		$this->assertSame( array( 'edit_posts', 'filtered', 'Plugin filtered' ), $seen );
	}

	/**
	 * A filter that returns something unusable falls back to manage_options and is flagged.
	 *
	 * @return void
	 */
	public function test_plugin_capability_filter_with_an_invalid_value_falls_back(): void {
		$this->setExpectedIncorrectUsage( self::NORMALIZE );
		$this->register( 'filtered', array( 'capability' => 'edit_posts' ) );
		add_filter( 'silverassist_settings_hub_plugin_capability', '__return_empty_string' );

		$this->assertSame( 'manage_options', $this->call_hub_method( 'get_plugin_capability', array( $this->stored( 'filtered' ) ) ) );
	}

	/**
	 * The Dashboard requires manage_options unless the filter changes it.
	 *
	 * @return void
	 */
	public function test_dashboard_capability_defaults_and_can_be_filtered(): void {
		$this->assertSame( 'manage_options', $this->call_hub_method( 'get_dashboard_capability' ) );

		add_filter(
			'silverassist_settings_hub_dashboard_capability',
			static function (): string {
				return 'edit_posts';
			}
		);

		$this->assertSame( 'edit_posts', $this->call_hub_method( 'get_dashboard_capability' ) );
	}

	/**
	 * The capability travels with the plugin data that action listeners receive.
	 *
	 * @return void
	 */
	public function test_capability_is_part_of_the_plugin_actions_payload(): void {
		$this->register( 'editors', array( 'capability' => 'edit_posts' ) );

		$received = array();
		add_action(
			'silverassist_settings_hub_plugin_actions',
			static function ( $slug, $plugin ) use ( &$received ): void {
				$received = $plugin;
			},
			10,
			2
		);

		$this->dashboard();

		$this->assertSame( 'edit_posts', $received['capability'] ?? null );
	}

	/**
	 * The dashboard lists a plugin only for users who hold its capability.
	 *
	 * @return void
	 */
	public function test_dashboard_lists_only_the_plugins_the_user_can_open(): void {
		$this->register( 'admin-only' );
		$this->register( 'for-editors', array( 'capability' => 'edit_posts' ) );

		$this->login_as( 'editor' );
		$as_editor = $this->dashboard();
		$this->assertStringContainsString( 'Plugin for-editors', $as_editor );
		$this->assertStringNotContainsString( 'Plugin admin-only', $as_editor );

		$this->login_as( 'administrator' );
		$as_admin = $this->dashboard();
		$this->assertStringContainsString( 'Plugin for-editors', $as_admin );
		$this->assertStringContainsString( 'Plugin admin-only', $as_admin );
	}

	/**
	 * The plugin count on the dashboard counts the cards the user sees, not every registration.
	 *
	 * @return void
	 */
	public function test_dashboard_count_matches_the_visible_cards(): void {
		$this->register( 'admin-only' );
		$this->register( 'another-admin-only' );
		$this->register( 'for-editors', array( 'capability' => 'edit_posts' ) );

		$this->login_as( 'editor' );
		$as_editor = $this->dashboard();
		$this->assertMatchesRegularExpression( '/silverassist-dashboard-count">\s*1 plugin\s*</', $as_editor );

		$this->login_as( 'administrator' );
		$this->assertMatchesRegularExpression( '/silverassist-dashboard-count">\s*3 plugins\s*</', $this->dashboard() );
	}

	/**
	 * A custom capability does not widen access for an administrator who lacks it.
	 *
	 * @return void
	 */
	public function test_an_administrator_without_the_custom_capability_does_not_see_the_plugin(): void {
		$this->register( 'woo-only', array( 'capability' => 'manage_woocommerce' ) );
		$this->register( 'plain' );

		$output = $this->dashboard();

		$this->assertStringNotContainsString( 'Plugin woo-only', $output );
		$this->assertStringContainsString( 'Plugin plain', $output );
	}

	/**
	 * Users who can open no plugin see the empty state instead of cards.
	 *
	 * @return void
	 */
	public function test_dashboard_shows_the_empty_state_when_nothing_is_visible(): void {
		$this->register( 'admin-only' );
		$this->login_as( 'subscriber' );

		$output = $this->dashboard();

		$this->assertStringContainsString( 'silverassist-empty-state', $output );
		$this->assertStringNotContainsString( 'silverassist-plugin-card', $output );
	}

	/**
	 * Without the dashboard capability the Dashboard tab is hidden, because it would be a dead link.
	 *
	 * @return void
	 */
	public function test_dashboard_tab_is_hidden_without_the_dashboard_capability(): void {
		$this->register( 'admin-only' );
		$this->register( 'for-editors', array( 'capability' => 'edit_posts' ) );
		$dashboard_href = 'href="' . esc_url( admin_url( 'admin.php?page=silver-assist' ) ) . '"';

		$this->login_as( 'editor' );
		$as_editor = $this->tabs( 'for-editors' );
		$this->assertStringNotContainsString( $dashboard_href, $as_editor );
		$this->assertStringContainsString( 'Plugin for-editors', $as_editor );
		$this->assertStringNotContainsString( 'Plugin admin-only', $as_editor );

		$this->login_as( 'administrator' );
		$as_admin = $this->tabs( 'for-editors' );
		$this->assertStringContainsString( $dashboard_href, $as_admin );
		$this->assertStringContainsString( 'Plugin admin-only', $as_admin );
	}

	/**
	 * Lowering the dashboard capability brings the Dashboard tab back for that role.
	 *
	 * @return void
	 */
	public function test_dashboard_capability_filter_restores_the_tab_for_lower_roles(): void {
		$this->register( 'for-editors', array( 'capability' => 'edit_posts' ) );
		add_filter(
			'silverassist_settings_hub_dashboard_capability',
			static function (): string {
				return 'edit_posts';
			}
		);
		$this->login_as( 'editor' );

		$this->assertStringContainsString(
			'href="' . esc_url( admin_url( 'admin.php?page=silver-assist' ) ) . '"',
			$this->tabs( 'for-editors' )
		);
	}

	/**
	 * Rendering a page the user may not open stops with a 403 before the plugin code runs.
	 *
	 * @return void
	 */
	public function test_plugin_page_dies_with_403_when_the_user_lacks_the_capability(): void {
		$ran = false;
		$this->hub()->register_plugin(
			'admin-only',
			'Admin Only',
			static function () use ( &$ran ): void {
				$ran = true;
			}
		);
		$this->login_as( 'editor' );

		// The WordPress test suite turns wp_die() into a WPDieException.
		$died    = null;
		$message = '';
		try {
			$this->render(
				function (): void {
					$this->call_hub_method( 'render_plugin_page', array( $this->stored( 'admin-only' ) ) );
				}
			);
		} catch ( \Exception $e ) {
			$died    = get_class( $e );
			$message = $e->getMessage();
		}

		$this->assertSame( 'WPDieException', $died, 'Expected wp_die() for a user without the capability.' );
		$this->assertStringContainsString( 'not allowed', $message );
		$this->assertFalse( $ran, 'The plugin callback must not run for a user without the capability.' );
	}

	/**
	 * A user who holds the plugin's capability gets the page.
	 *
	 * @return void
	 */
	public function test_plugin_page_renders_for_a_user_with_the_capability(): void {
		$this->register( 'for-editors', array( 'capability' => 'edit_posts' ) );
		$this->login_as( 'editor' );

		$output = $this->render(
			function (): void {
				$this->call_hub_method( 'render_plugin_page', array( $this->stored( 'for-editors' ) ) );
			}
		);

		$this->assertStringContainsString( 'page-of-for-editors', $output );
	}
}
