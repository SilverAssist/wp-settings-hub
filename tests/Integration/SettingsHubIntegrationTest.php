<?php
/**
 * Integration Tests for Settings Hub
 *
 * Tests the hub against the real WordPress admin menu system: the $menu and
 * $submenu globals, the page access check and the callbacks WordPress attaches
 * to each registered page.
 *
 * @package SilverAssist\SettingsHub\Tests\Integration
 */

declare(strict_types=1);

namespace SilverAssist\SettingsHub\Tests\Integration;

use SilverAssist\SettingsHub\Tests\TestCase;

/**
 * Integration test suite for the Settings Hub menu, access control and page rendering.
 *
 * Rule for this file: a test reads $menu, $submenu, user_can_access_admin_page()
 * (through can_access_page()) or the output of the callback WordPress registered
 * (through render_registered_page()). It never calls the hub's render methods or
 * get_plugins() directly, because those belong to the unit tier.
 */
class SettingsHubIntegrationTest extends TestCase {
	/**
	 * Register the two plugins most tests work with.
	 *
	 * @return void
	 */
	private function register_two_plugins(): void {
		$this->hub()->register_plugin(
			'plugin-1',
			'Plugin One',
			$this->marker_callback( 'marker-one' ),
			array(
				'description' => 'First plugin description',
				'version'     => '1.2.3',
			)
		);

		$this->hub()->register_plugin(
			'plugin-2',
			'Plugin Two',
			$this->marker_callback( 'marker-two' ),
			array(
				'description' => 'Second plugin description',
				'version'     => '4.5.6',
			)
		);
	}

	/**
	 * Build a plugin callback that prints a recognisable marker.
	 *
	 * @param string $marker Text the callback prints.
	 *
	 * @return callable
	 */
	private function marker_callback( string $marker ): callable {
		return static function () use ( $marker ): void {
			echo '<div class="plugin-body">' . esc_html( $marker ) . '</div>';
		};
	}

	/**
	 * Get the top-level $menu entries registered under a slug, keyed by position.
	 *
	 * @param string $slug Menu slug (the third element of a menu entry).
	 *
	 * @return array<int|string, array<int, mixed>>
	 */
	private function menu_entries( string $slug ): array {
		$entries = array();

		foreach ( (array) ( $GLOBALS['menu'] ?? array() ) as $position => $entry ) {
			if ( is_array( $entry ) && isset( $entry[2] ) && $slug === $entry[2] ) {
				$entries[ $position ] = $entry;
			}
		}

		return $entries;
	}

	/**
	 * Get the rows WordPress stored in $submenu for a parent slug.
	 *
	 * @param string $parent Parent menu slug.
	 *
	 * @return array<int, array<int, mixed>>
	 */
	private function submenu_rows( string $parent ): array {
		$submenu = (array) ( $GLOBALS['submenu'] ?? array() );

		return array_values( (array) ( $submenu[ $parent ] ?? array() ) );
	}

	/**
	 * Parse rendered HTML into an XPath object.
	 *
	 * @param string $html Rendered page output.
	 *
	 * @return \DOMXPath
	 */
	private function xpath( string $html ): \DOMXPath {
		$dom      = new \DOMDocument();
		$previous = libxml_use_internal_errors( true );

		$dom->loadHTML( '<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>' );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		return new \DOMXPath( $dom );
	}

	/**
	 * XPath predicate matching an element that has a given class token.
	 *
	 * @param string $class_name The class token.
	 *
	 * @return string
	 */
	private function has_class( string $class_name ): string {
		return "contains(concat(' ', normalize-space(@class), ' '), ' " . $class_name . " ')";
	}

	/**
	 * Run an XPath query and return the matching nodes.
	 *
	 * @param \DOMXPath $xpath Parsed page.
	 * @param string    $query XPath expression.
	 *
	 * @return array<int, \DOMNode>
	 */
	private function nodes( \DOMXPath $xpath, string $query ): array {
		$result = $xpath->query( $query );

		return false === $result ? array() : array_values( iterator_to_array( $result ) );
	}

	/**
	 * Collapse whitespace in a node's text.
	 *
	 * @param \DOMNode $node The node.
	 *
	 * @return string
	 */
	private function node_text( \DOMNode $node ): string {
		return trim( (string) preg_replace( '/\s+/', ' ', $node->textContent ) );
	}

	/**
	 * Read the navigation tabs from a rendered page.
	 *
	 * @param string $html Rendered page output.
	 *
	 * @return array<int, array{label: string, active: bool}> The tabs in document order.
	 */
	private function tabs( string $html ): array {
		$xpath = $this->xpath( $html );
		$tabs  = array();

		foreach ( $this->nodes( $xpath, '//nav[' . $this->has_class( 'nav-tab-wrapper' ) . ']//a' ) as $link ) {
			if ( ! $link instanceof \DOMElement ) {
				continue;
			}

			$tokens = preg_split( '/\s+/', trim( $link->getAttribute( 'class' ) ) );
			$tabs[] = array(
				'label'  => $this->node_text( $link ),
				'active' => in_array( 'nav-tab-active', (array) $tokens, true ),
			);
		}

		return $tabs;
	}

	/**
	 * Registering plugins and firing admin_menu builds the parent menu and the submenu in order.
	 *
	 * @return void
	 */
	public function test_menu_registers_parent_dashboard_and_each_plugin_in_submenu(): void {
		$this->login_as( 'administrator' );
		$this->register_two_plugins();
		$this->fire_admin_menu();

		$parents = $this->menu_entries( 'silver-assist' );
		$this->assertCount( 1, $parents, 'The parent menu should be registered exactly once' );

		$position = array_key_first( $parents );
		$parent   = $parents[ $position ];

		$this->assertSame( 80, $position, 'The parent menu should sit at position 80' );
		$this->assertSame( 'Silver Assist', $parent[0], 'Menu title' );
		$this->assertSame( 'manage_options', $parent[1], 'Menu capability' );
		$this->assertSame( 'Silver Assist', $parent[3], 'Page title' );
		$this->assertIsString( $parent[6] );
		$this->assertStringStartsWith( 'data:image/svg+xml;base64,', $parent[6], 'The icon should be an inline SVG data URI' );

		$this->assertSame(
			array(
				array( 'Dashboard', 'manage_options', 'silver-assist', 'Dashboard' ),
				array( 'Plugin One', 'manage_options', 'plugin-1', 'Plugin One' ),
				array( 'Plugin Two', 'manage_options', 'plugin-2', 'Plugin Two' ),
			),
			$this->submenu_rows( 'silver-assist' ),
			'The submenu should list Dashboard, then each plugin in registration order'
		);
	}

	/**
	 * The submenu follows registration order, not alphabetical order.
	 *
	 * @return void
	 */
	public function test_submenu_keeps_registration_order(): void {
		$this->login_as( 'administrator' );

		foreach ( array( 'zeta', 'alpha', 'mid' ) as $slug ) {
			$this->hub()->register_plugin( $slug, 'Plugin ' . $slug, $this->marker_callback( $slug ) );
		}

		$this->fire_admin_menu();

		$slugs = array_column( $this->submenu_rows( 'silver-assist' ), 2 );

		$this->assertSame( array( 'silver-assist', 'zeta', 'alpha', 'mid' ), $slugs );
	}

	/**
	 * A tab title replaces the plugin name in the submenu while the page title stays the name.
	 *
	 * @return void
	 */
	public function test_submenu_uses_tab_title_over_name(): void {
		$this->login_as( 'administrator' );

		$this->hub()->register_plugin(
			'titled',
			'Long Plugin Name',
			$this->marker_callback( 'titled-body' ),
			array( 'tab_title' => 'Short' )
		);
		$this->hub()->register_plugin( 'plain', 'Plain Plugin', $this->marker_callback( 'plain-body' ) );
		$this->fire_admin_menu();

		$rows = $this->submenu_rows( 'silver-assist' );

		$this->assertSame( array( 'Short', 'manage_options', 'titled', 'Long Plugin Name' ), $rows[1], 'Menu title is the tab title, page title is the name' );
		$this->assertSame( array( 'Plain Plugin', 'manage_options', 'plain', 'Plain Plugin' ), $rows[2], 'Without a tab title the name is used for both' );

		$labels = array_column( $this->tabs( $this->render_registered_page( 'titled' ) ), 'label' );
		$this->assertSame( array( 'Dashboard', 'Short', 'Plain Plugin' ), $labels, 'The tab navigation should use the same tab title' );
	}

	/**
	 * A plugin registered without description or version still gets a menu entry and a page.
	 *
	 * @return void
	 */
	public function test_plugin_without_description_or_version_still_appears(): void {
		$this->login_as( 'administrator' );
		$this->hub()->register_plugin( 'bare', 'Bare Plugin', $this->marker_callback( 'bare-body' ) );
		$this->fire_admin_menu();

		$this->assertSame(
			array( 'Bare Plugin', 'manage_options', 'bare', 'Bare Plugin' ),
			$this->submenu_rows( 'silver-assist' )[1]
		);

		$page  = $this->render_registered_page( 'bare' );
		$xpath = $this->xpath( $page );

		$this->assertStringContainsString( 'bare-body', $page );
		$this->assertCount( 0, $this->nodes( $xpath, '//span[' . $this->has_class( 'version-badge' ) . ']' ), 'No version badge without a version' );

		$cards = $this->nodes( $this->xpath( $this->render_registered_page( 'silver-assist' ) ), '//div[' . $this->has_class( 'silverassist-plugin-card' ) . ']' );
		$this->assertCount( 1, $cards, 'The dashboard should still list the plugin' );
		$this->assertCount( 0, $this->nodes( $this->xpath( (string) $cards[0]->ownerDocument?->saveHTML( $cards[0] ) ), '//span[' . $this->has_class( 'silverassist-version-badge' ) . ']' ), 'No version badge on the card' );
	}

	/**
	 * A plugin page rendered through WordPress's callback shows the name, version badge and the plugin body.
	 *
	 * @return void
	 */
	public function test_plugin_page_renders_through_the_registered_callback(): void {
		$this->login_as( 'administrator' );
		$this->register_two_plugins();
		$this->fire_admin_menu();

		$page  = $this->render_registered_page( 'plugin-1' );
		$xpath = $this->xpath( $page );

		$headings = $this->nodes( $xpath, '//div[' . $this->has_class( 'wrap' ) . ']/h1' );
		$this->assertCount( 1, $headings, 'The page should have a single h1' );
		$this->assertStringContainsString( 'Plugin One', $this->node_text( $headings[0] ) );

		$badges = $this->nodes( $xpath, '//h1/span[' . $this->has_class( 'version-badge' ) . ']' );
		$this->assertCount( 1, $badges, 'The version badge should be inside the h1' );
		$this->assertSame( 'v1.2.3', $this->node_text( $badges[0] ) );

		$body = $this->nodes( $xpath, '//div[' . $this->has_class( 'plugin-body' ) . ']' );
		$this->assertCount( 1, $body, 'The plugin callback should run exactly once' );
		$this->assertSame( 'marker-one', $this->node_text( $body[0] ) );
		$this->assertStringNotContainsString( 'marker-two', $page, 'Only the requested plugin renders' );
	}

	/**
	 * The dashboard rendered through WordPress's callback lists a card per registered plugin.
	 *
	 * @return void
	 */
	public function test_dashboard_renders_through_the_registered_callback(): void {
		$this->login_as( 'administrator' );
		$this->register_two_plugins();
		$this->fire_admin_menu();

		$xpath = $this->xpath( $this->render_registered_page( 'silver-assist' ) );
		$cards = $this->nodes( $xpath, '//div[' . $this->has_class( 'silverassist-plugin-card' ) . ']' );

		$this->assertCount( 2, $cards, 'One card per registered plugin' );

		$expected = array(
			array( 'Plugin One', 'v1.2.3', 'First plugin description', 'plugin-1' ),
			array( 'Plugin Two', 'v4.5.6', 'Second plugin description', 'plugin-2' ),
		);

		foreach ( $cards as $index => $card ) {
			$card_xpath = $this->xpath( (string) $card->ownerDocument?->saveHTML( $card ) );
			$title      = $this->nodes( $card_xpath, '//h2' );
			$badge      = $this->nodes( $card_xpath, '//span[' . $this->has_class( 'silverassist-version-badge' ) . ']' );
			$desc       = $this->nodes( $card_xpath, '//p[' . $this->has_class( 'card-description' ) . ']' );
			$link       = $this->nodes( $card_xpath, '//a[' . $this->has_class( 'button-primary' ) . ']' );

			$this->assertNotEmpty( $title );
			$this->assertNotEmpty( $badge );
			$this->assertNotEmpty( $desc );
			$this->assertNotEmpty( $link );
			$this->assertSame( $expected[ $index ][0], $this->node_text( $title[0] ) );
			$this->assertSame( $expected[ $index ][1], $this->node_text( $badge[0] ) );
			$this->assertSame( $expected[ $index ][2], $this->node_text( $desc[0] ) );
			$href = $link[0] instanceof \DOMElement ? $link[0]->getAttribute( 'href' ) : '';
			$this->assertStringContainsString( 'admin.php?page=' . $expected[ $index ][3], $href );
		}
	}

	/**
	 * Only the tab of the current page is marked active, and no navigation appears when tabs are off.
	 *
	 * @return void
	 */
	public function test_active_tab_is_marked_on_dashboard_and_on_plugin_page(): void {
		$this->login_as( 'administrator' );
		$this->register_two_plugins();
		$this->fire_admin_menu();

		$this->assertSame(
			array(
				array(
					'label'  => 'Dashboard',
					'active' => true,
				),
				array(
					'label'  => 'Plugin One',
					'active' => false,
				),
				array(
					'label'  => 'Plugin Two',
					'active' => false,
				),
			),
			$this->tabs( $this->render_registered_page( 'silver-assist' ) ),
			'On the dashboard only the Dashboard tab is active'
		);

		$this->assertSame(
			array(
				array(
					'label'  => 'Dashboard',
					'active' => false,
				),
				array(
					'label'  => 'Plugin One',
					'active' => true,
				),
				array(
					'label'  => 'Plugin Two',
					'active' => false,
				),
			),
			$this->tabs( $this->render_registered_page( 'plugin-1' ) ),
			'On plugin one only its own tab is active'
		);

		$this->assertSame(
			array(
				array(
					'label'  => 'Dashboard',
					'active' => false,
				),
				array(
					'label'  => 'Plugin One',
					'active' => false,
				),
				array(
					'label'  => 'Plugin Two',
					'active' => true,
				),
			),
			$this->tabs( $this->render_registered_page( 'plugin-2' ) ),
			'On plugin two only its own tab is active'
		);
	}

	/**
	 * With tabs disabled neither the dashboard nor a plugin page renders a navigation element.
	 *
	 * @return void
	 */
	public function test_no_navigation_is_rendered_when_tabs_are_disabled(): void {
		$this->login_as( 'administrator' );
		$this->register_two_plugins();
		$this->hub()->enable_tabs( false );
		$this->fire_admin_menu();

		foreach ( array( 'silver-assist', 'plugin-1' ) as $slug ) {
			$page  = $this->render_registered_page( $slug );
			$xpath = $this->xpath( $page );

			$this->assertCount( 1, $this->nodes( $xpath, '//h1' ), "The {$slug} page should still render" );
			$this->assertCount( 0, $this->nodes( $xpath, '//nav' ), "No nav element on {$slug}" );
			$this->assertSame( array(), $this->tabs( $page ), "No tabs on {$slug}" );
		}
	}

	/**
	 * An administrator may open the dashboard and every plugin page, and an unknown page stays closed.
	 *
	 * Phase 3 (per-plugin capability) will extend this with plugins that require another capability.
	 *
	 * @return void
	 */
	public function test_administrator_can_access_every_hub_page(): void {
		$this->login_as( 'administrator' );
		$this->register_two_plugins();
		$this->fire_admin_menu();

		foreach ( array( 'silver-assist', 'plugin-1', 'plugin-2' ) as $slug ) {
			$this->assertTrue( $this->can_access_page( $slug ), "An administrator should reach {$slug}" );
		}

		$this->assertFalse( $this->can_access_page( 'not-registered' ), 'A page nobody registered is never accessible' );
	}

	/**
	 * Roles without manage_options cannot open any hub page.
	 *
	 * Phase 3 (per-plugin capability) will extend this with plugins that open to lower roles.
	 *
	 * @dataProvider provide_non_admin_roles
	 *
	 * @param string $role WordPress role without manage_options.
	 *
	 * @return void
	 */
	public function test_editor_and_subscriber_cannot_access_hub_pages( string $role ): void {
		$this->login_as( $role );
		$this->register_two_plugins();
		$this->fire_admin_menu();

		foreach ( array( 'silver-assist', 'plugin-1', 'plugin-2' ) as $slug ) {
			$this->assertFalse( $this->can_access_page( $slug ), "A {$role} should not reach {$slug}" );
		}
	}

	/**
	 * Roles that must be locked out of the hub.
	 *
	 * @return array<string, array{string}>
	 */
	public static function provide_non_admin_roles(): array {
		return array(
			'editor'     => array( 'editor' ),
			'subscriber' => array( 'subscriber' ),
		);
	}

	/**
	 * WordPress never attaches the callbacks for a user without the capability, so nothing renders.
	 *
	 * Phase 3 (per-plugin capability) will extend this.
	 *
	 * @return void
	 */
	public function test_editor_gets_no_page_output_from_the_registered_callbacks(): void {
		$this->login_as( 'editor' );
		$this->register_two_plugins();
		$this->fire_admin_menu();

		foreach ( array( 'silver-assist', 'plugin-1', 'plugin-2' ) as $slug ) {
			$this->assertSame( '', $this->render_registered_page( $slug ), "No output for an editor on {$slug}" );
		}
	}

	/**
	 * The hub hooks admin_menu only, never the network or user admin menus.
	 *
	 * @return void
	 */
	public function test_hub_registers_nothing_in_the_network_admin(): void {
		$callback = array( $this->hub(), 'register_menus' );

		$this->register_two_plugins();

		$this->assertFalse( has_action( 'network_admin_menu', $callback ), 'Not hooked to network_admin_menu' );
		$this->assertFalse( has_action( 'user_admin_menu', $callback ), 'Not hooked to user_admin_menu' );

		if ( is_multisite() ) {
			$this->login_as( 'administrator' );
			$this->fire_admin_menu();

			$this->assertCount( 1, $this->menu_entries( 'silver-assist' ), 'The sub-site admin menu still gets the hub' );
		}
	}

	/**
	 * Registering a plugin hooks register_menus onto admin_menu at priority 5, and not before.
	 *
	 * @return void
	 */
	public function test_register_plugin_hooks_register_menus_at_priority_5(): void {
		$callback = array( $this->hub(), 'register_menus' );

		$this->assertFalse( has_action( 'admin_menu', $callback ), 'Nothing is hooked before a plugin registers' );

		$this->register_two_plugins();

		$this->assertSame( 5, has_action( 'admin_menu', $callback ), 'register_menus runs at priority 5, once' );
	}
}
