<?php
/**
 * WordPress-driven tests for the per-plugin capability of the Settings Hub.
 *
 * @package SilverAssist\SettingsHub\Tests\Integration
 */

declare(strict_types=1);

namespace SilverAssist\SettingsHub\Tests\Integration;

use SilverAssist\SettingsHub\Tests\TestCase;

/**
 * Checks what WordPress does with the capabilities the hub passes to
 * add_menu_page() and add_submenu_page(): which menu rows survive for a role,
 * which pages the access check opens, and what the registered callbacks print.
 *
 * Same rule as SettingsHubIntegrationTest: read $menu, $submenu,
 * user_can_access_admin_page() (through can_access_page()) or the output of the
 * callback WordPress registered (through render_registered_page()).
 */
class CapabilityIntegrationTest extends TestCase {
	/**
	 * Register one administrator-only plugin and one that editors can open.
	 *
	 * @return void
	 */
	private function register_mixed_plugins(): void {
		$this->hub()->register_plugin(
			'admin-only',
			'Admin Only',
			static function (): void {
				echo '<div class="plugin-body">marker-admin</div>';
			}
		);

		$this->hub()->register_plugin(
			'editor-page',
			'Editor Page',
			static function (): void {
				echo '<div class="plugin-body">marker-editor</div>';
			},
			array( 'capability' => 'edit_posts' )
		);
	}

	/**
	 * Rows WordPress stored under the hub's menu.
	 *
	 * @return array<int|string, mixed>
	 */
	private function submenu_rows(): array {
		return (array) ( $GLOBALS['submenu']['silver-assist'] ?? array() );
	}

	/**
	 * Slugs WordPress kept under the hub's menu for the current user.
	 *
	 * @return string[]
	 */
	private function submenu_slugs(): array {
		return array_values( array_map( static fn( $row ): string => (string) $row[2], $this->submenu_rows() ) );
	}

	/**
	 * Labels of the navigation tabs in a rendered page.
	 *
	 * @param string $html Rendered page.
	 *
	 * @return string[]
	 */
	private function tab_labels( string $html ): array {
		$dom      = new \DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>' );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		$labels = array();
		foreach ( ( new \DOMXPath( $dom ) )->query( '//nav//a' ) ?: array() as $link ) {
			$labels[] = trim( $link->textContent );
		}

		return $labels;
	}

	/**
	 * An editor keeps only the page their capability opens.
	 *
	 * @return void
	 */
	public function test_editor_sees_only_the_page_their_capability_opens(): void {
		$this->register_mixed_plugins();
		$this->login_as( 'editor' );
		$this->fire_admin_menu();

		$this->assertSame( array( 'editor-page' ), $this->submenu_slugs() );
		$this->assertTrue( $this->can_access_page( 'editor-page' ) );
	}

	/**
	 * An editor is refused the Dashboard and the administrator-only plugin.
	 *
	 * @return void
	 */
	public function test_editor_is_denied_the_dashboard_and_administrator_only_pages(): void {
		$this->register_mixed_plugins();
		$this->login_as( 'editor' );
		$this->fire_admin_menu();

		$this->assertFalse( $this->can_access_page( 'silver-assist' ), 'The Dashboard still requires manage_options' );
		$this->assertFalse( $this->can_access_page( 'admin-only' ), 'A plugin without a capability still requires manage_options' );
	}

	/**
	 * An administrator keeps every row and can open every page.
	 *
	 * @return void
	 */
	public function test_administrator_sees_and_opens_everything(): void {
		$this->register_mixed_plugins();
		$this->login_as( 'administrator' );
		$this->fire_admin_menu();

		$this->assertSame( array( 'silver-assist', 'admin-only', 'editor-page' ), $this->submenu_slugs() );
		foreach ( array( 'silver-assist', 'admin-only', 'editor-page' ) as $slug ) {
			$this->assertTrue( $this->can_access_page( $slug ), "An administrator should open {$slug}" );
		}
	}

	/**
	 * A subscriber holds neither capability and loses every row.
	 *
	 * @return void
	 */
	public function test_subscriber_gets_no_hub_pages(): void {
		$this->register_mixed_plugins();
		$this->login_as( 'subscriber' );
		$this->fire_admin_menu();

		$this->assertSame( array(), $this->submenu_slugs() );
		foreach ( array( 'silver-assist', 'admin-only', 'editor-page' ) as $slug ) {
			$this->assertFalse( $this->can_access_page( $slug ), "A subscriber should not open {$slug}" );
		}
	}

	/**
	 * The page callback WordPress attaches runs for the editor on their page only.
	 *
	 * @return void
	 */
	public function test_registered_callbacks_run_only_for_users_who_hold_the_capability(): void {
		$this->register_mixed_plugins();
		$this->login_as( 'editor' );
		$this->fire_admin_menu();

		$this->assertStringContainsString( 'marker-editor', $this->render_registered_page( 'editor-page' ) );
		$this->assertSame( '', $this->render_registered_page( 'admin-only' ), 'WordPress never attached the callback' );
		$this->assertSame( '', $this->render_registered_page( 'silver-assist' ), 'The Dashboard callback was not attached either' );
	}

	/**
	 * The editor's page shows only the tabs they can follow.
	 *
	 * @return void
	 */
	public function test_editor_page_has_no_dashboard_tab_and_no_admin_tab(): void {
		$this->register_mixed_plugins();
		$this->login_as( 'editor' );
		$this->fire_admin_menu();

		$this->assertSame( array( 'Editor Page' ), $this->tab_labels( $this->render_registered_page( 'editor-page' ) ) );
	}

	/**
	 * The administrator sees every tab on the same page.
	 *
	 * @return void
	 */
	public function test_administrator_page_has_every_tab(): void {
		$this->register_mixed_plugins();
		$this->login_as( 'administrator' );
		$this->fire_admin_menu();

		$this->assertSame(
			array( 'Dashboard', 'Admin Only', 'Editor Page' ),
			$this->tab_labels( $this->render_registered_page( 'editor-page' ) )
		);
	}

	/**
	 * The top-level menu and the Dashboard use the dashboard capability, not a plugin's.
	 *
	 * @return void
	 */
	public function test_parent_menu_and_dashboard_use_manage_options_by_default(): void {
		$this->register_mixed_plugins();
		$this->login_as( 'administrator' );
		$this->fire_admin_menu();

		$parent = array_values(
			array_filter(
				(array) $GLOBALS['menu'],
				static fn( $entry ): bool => is_array( $entry ) && 'silver-assist' === ( $entry[2] ?? '' )
			)
		);

		$this->assertCount( 1, $parent );
		$this->assertSame( 'manage_options', $parent[0][1] );
		$this->assertSame( 'manage_options', $GLOBALS['submenu']['silver-assist'][0][1] );
	}

	/**
	 * Lowering the dashboard capability opens the Dashboard to that role.
	 *
	 * @return void
	 */
	public function test_dashboard_capability_filter_opens_the_dashboard_to_editors(): void {
		$this->register_mixed_plugins();
		add_filter(
			'silverassist_settings_hub_dashboard_capability',
			static function (): string {
				return 'edit_posts';
			}
		);
		$this->login_as( 'editor' );
		$this->fire_admin_menu();

		$this->assertSame( array( 'silver-assist', 'editor-page' ), $this->submenu_slugs() );
		$this->assertTrue( $this->can_access_page( 'silver-assist' ) );
		$this->assertFalse( $this->can_access_page( 'admin-only' ), 'Plugins keep their own capability' );
		$this->assertStringContainsString( 'Editor Page', $this->render_registered_page( 'silver-assist' ) );
		$this->assertStringNotContainsString( 'Admin Only', $this->render_registered_page( 'silver-assist' ) );
	}

	/**
	 * The plugin capability filter is resolved when the menu is built, so late filters apply.
	 *
	 * @return void
	 */
	public function test_plugin_capability_filter_applies_when_the_menu_is_built(): void {
		$this->register_mixed_plugins();
		add_filter(
			'silverassist_settings_hub_plugin_capability',
			static function ( $capability, $slug ) {
				return 'admin-only' === $slug ? 'read' : $capability;
			},
			10,
			2
		);
		$this->login_as( 'subscriber' );
		$this->fire_admin_menu();

		$this->assertSame( array( 'admin-only' ), $this->submenu_slugs() );
		$this->assertTrue( $this->can_access_page( 'admin-only' ) );
		$this->assertFalse( $this->can_access_page( 'editor-page' ), 'edit_posts is still above a subscriber' );
	}

	/**
	 * A caller that passes manage_options explicitly, as silver-assist-security does, builds the same menu as one that passes nothing.
	 *
	 * @return void
	 */
	public function test_explicit_default_capability_builds_the_same_menu_as_none(): void {
		$callback = static function (): void {
			echo 'page';
		};
		$this->login_as( 'administrator' );

		$bare = $this->hub();
		$bare->register_plugin( 'consumer', 'Consumer', $callback, array( 'version' => '1.0.0' ) );
		$this->fire_admin_menu();
		$without = $this->submenu_rows();

		// Start over with a second hub, passing the capability the consumer passes today.
		remove_action( 'admin_menu', array( $bare, 'register_menus' ), 5 );
		$this->reset_hub();
		$GLOBALS['menu']    = array();
		$GLOBALS['submenu'] = array();
		unset( $GLOBALS['wp_actions']['admin_menu'] ); // A new request has not fired admin_menu yet.

		$this->hub()->register_plugin( 'consumer', 'Consumer', $callback, array(
			'version'    => '1.0.0',
			'capability' => 'manage_options',
		) );
		$this->fire_admin_menu();

		$this->assertSame( $without, $this->submenu_rows() );
	}
}
