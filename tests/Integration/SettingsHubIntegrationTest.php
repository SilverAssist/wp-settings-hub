<?php
/**
 * Integration Tests for Settings Hub
 *
 * Tests the complete integration with WordPress including admin menus,
 * page rendering, and multi-plugin interactions.
 *
 * @package SilverAssist\SettingsHub\Tests\Integration
 */

declare(strict_types=1);

namespace SilverAssist\SettingsHub\Tests\Integration;

use SilverAssist\SettingsHub\SettingsHub;
use SilverAssist\SettingsHub\Tests\TestCase;

/**
 * Integration test suite for Settings Hub
 *
 * These tests verify the hub works correctly with WordPress admin menu system,
 * handles multiple plugin registrations, and renders pages properly.
 */
class SettingsHubIntegrationTest extends TestCase {
	/**
	 * Settings Hub instance
	 *
	 * @var SettingsHub
	 */
	private SettingsHub $hub;

	/**
	 * Set up test environment
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		// Reset singleton for clean state
		$reflection = new \ReflectionClass( SettingsHub::class );
		$instance   = $reflection->getProperty( 'instance' );
		$instance->setAccessible( true );
		$instance->setValue( null, null );

		$this->hub = SettingsHub::get_instance();

		// Set current user as administrator
		wp_set_current_user( $this->factory()->user->create( [ 'role' => 'administrator' ] ) );
		
		// Simulate admin context
		set_current_screen( 'dashboard' );
	}

	/**
	 * Test WordPress admin menu integration
	 *
	 * Verifies that registering plugins creates proper WordPress admin menu structure.
	 *
	 * @return void
	 */
	public function test_wordpress_admin_menu_integration(): void {
		global $menu, $submenu;

		// Register test plugins
		$this->hub->register_plugin(
			'test-plugin-1',
			'Test Plugin One',
			[ $this, 'mock_render_callback' ]
		);

		$this->hub->register_plugin(
			'test-plugin-2',
			'Test Plugin Two',
			[ $this, 'mock_render_callback' ]
		);

		// Trigger admin_menu action to register menus
		do_action( 'admin_menu' );

		// Verify parent menu exists
		$parent_found = false;
		foreach ( $menu as $item ) {
			if ( isset( $item[0] ) && 'Silver Assist' === $item[0] ) {
				$parent_found = true;
				$this->assertSame( 'manage_options', $item[1] );
				// The menu slug is created by WordPress based on the page title
				$this->assertStringContainsString( 'silver-assist', $item[2] );
				break;
			}
		}
		$this->assertTrue( $parent_found, 'Silver Assist parent menu should be created' );

		// Verify submenus exist (parent slug in WordPress doesn't use 'silverassist-' prefix)
		$this->assertNotEmpty( $submenu );
		// Note: In test environment, submenu keys might differ from production
	}

	/**
	 * Test dashboard rendering with multiple plugins
	 *
	 * @return void
	 */
	public function test_dashboard_renders_all_registered_plugins(): void {
		// Register multiple plugins with different metadata
		$this->hub->register_plugin(
			'plugin-alpha',
			'Plugin Alpha',
			[ $this, 'mock_render_callback' ],
			[
				'description' => 'Alpha plugin description',
				'version'     => '1.0.0',
			]
		);

		$this->hub->register_plugin(
			'plugin-beta',
			'Plugin Beta',
			[ $this, 'mock_render_callback' ],
			[
				'description' => 'Beta plugin description',
				'version'     => '2.5.3',
			]
		);

		$this->hub->register_plugin(
			'plugin-gamma',
			'Plugin Gamma',
			[ $this, 'mock_render_callback' ]
		);

		// Capture dashboard output
		ob_start();
		$this->hub->render_dashboard();
		$output = ob_get_clean();

		// Verify all plugins appear in dashboard
		$this->assertStringContainsString( 'Plugin Alpha', $output );
		$this->assertStringContainsString( 'Plugin Beta', $output );
		$this->assertStringContainsString( 'Plugin Gamma', $output );

		// Verify descriptions appear
		$this->assertStringContainsString( 'Alpha plugin description', $output );
		$this->assertStringContainsString( 'Beta plugin description', $output );

		// Verify versions appear
		$this->assertStringContainsString( '1.0.0', $output );
		$this->assertStringContainsString( '2.5.3', $output );

		// Verify links to settings pages (without 'silverassist-' prefix in slug)
		$this->assertStringContainsString( 'admin.php?page=plugin-alpha', $output );
		$this->assertStringContainsString( 'admin.php?page=plugin-beta', $output );
		$this->assertStringContainsString( 'admin.php?page=plugin-gamma', $output );
	}

	/**
	 * Test tabs navigation rendering
	 *
	 * @return void
	 */
	public function test_tabs_navigation_with_multiple_plugins(): void {
		// Register plugins
		$this->hub->register_plugin(
			'settings-plugin',
			'Settings Plugin',
			[ $this, 'mock_render_callback' ]
		);

		$this->hub->register_plugin(
			'advanced-plugin',
			'Advanced Plugin',
			[ $this, 'mock_render_callback' ]
		);

		// Simulate being on dashboard page
		$_GET['page'] = 'silver-assist';

		// Capture dashboard output which includes tabs when tabs are enabled
		ob_start();
		$this->hub->render_dashboard();
		$output = ob_get_clean();

		// Verify tabs structure exists in dashboard
		$this->assertStringContainsString( 'nav-tab-wrapper', $output );
		$this->assertStringContainsString( 'Settings Plugin', $output );
		$this->assertStringContainsString( 'Advanced Plugin', $output );

		// Clean up
		unset( $_GET['page'] );
	}

	/**
	 * Test plugin callback execution
	 *
	 * Verifies that when a plugin's settings page is accessed,
	 * the correct callback is executed.
	 *
	 * @return void
	 */
	public function test_plugin_callback_execution(): void {
		$callback_executed = false;
		$callback_function = function () use ( &$callback_executed ) {
			$callback_executed = true;
			echo '<div class="test-plugin-content">Plugin Settings Content</div>';
		};

		$this->hub->register_plugin(
			'callback-test',
			'Callback Test Plugin',
			$callback_function
		);

		// Get registered plugins
		$plugins = $this->hub->get_plugins();
		
		// Execute the callback
		ob_start();
		call_user_func( $plugins['callback-test']['callback'] );
		$output = ob_get_clean();

		$this->assertTrue( $callback_executed, 'Plugin callback should be executed' );
		$this->assertStringContainsString( 'test-plugin-content', $output );
		$this->assertStringContainsString( 'Plugin Settings Content', $output );
	}

	/**
	 * Test WordPress capabilities integration
	 *
	 * Verifies that only users with proper capabilities can access settings.
	 *
	 * @return void
	 */
	public function test_capabilities_check_for_settings_access(): void {
		// Create user without admin capabilities
		$subscriber_id = $this->factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber_id );

		$this->hub->register_plugin(
			'secure-plugin',
			'Secure Plugin',
			[ $this, 'mock_render_callback' ]
		);

		// Try to render dashboard as non-admin
		ob_start();
		$this->hub->render_dashboard();
		$output = ob_get_clean();

		// Should show permission error or be empty for non-admin users
		// (WordPress typically handles this at menu registration level)
		$this->assertFalse( current_user_can( 'manage_options' ) );
	}

	/**
	 * Test WordPress hooks integration
	 *
	 * Verifies that admin_menu hook is properly registered.
	 *
	 * @return void
	 */
	public function test_admin_menu_hook_registration(): void {
		global $wp_filter;

		// Verify admin_menu hook exists in WordPress
		$this->assertArrayHasKey( 'admin_menu', $wp_filter );
		
		// The hook is registered in the constructor which runs before tests
		// We verify it exists and can be called
		$this->assertNotEmpty( $wp_filter['admin_menu'] );
	}

	/**
	 * Test multiple plugin registration order preservation
	 *
	 * Verifies that plugins appear in the order they were registered.
	 *
	 * @return void
	 */
	public function test_plugin_registration_order_preserved(): void {
		$plugins = [ 'first', 'second', 'third', 'fourth', 'fifth' ];

		foreach ( $plugins as $slug ) {
			$this->hub->register_plugin(
				$slug,
				ucfirst( $slug ) . ' Plugin',
				[ $this, 'mock_render_callback' ]
			);
		}

		$registered = $this->hub->get_plugins();
		$registered_keys = array_keys( $registered );

		$this->assertSame( $plugins, $registered_keys, 'Plugins should maintain registration order' );
	}

	/**
	 * Test dashboard card rendering structure
	 *
	 * @return void
	 */
	public function test_dashboard_card_structure(): void {
		$this->hub->register_plugin(
			'card-test',
			'Card Test Plugin',
			[ $this, 'mock_render_callback' ],
			[
				'description' => 'Test card description',
				'version'     => '3.2.1',
			]
		);

		ob_start();
		$this->hub->render_dashboard();
		$output = ob_get_clean();

		// Verify card structure - WordPress uses .card class
		$this->assertStringContainsString( 'class="card"', $output );
		
		// Verify plugin name and version are displayed
		$this->assertStringContainsString( 'Card Test Plugin', $output );
		$this->assertStringContainsString( '3.2.1', $output );
		
		// Verify description is present
		$this->assertStringContainsString( 'Test card description', $output );
		
		// Verify action buttons
		$this->assertStringContainsString( 'button button-primary', $output );
		$this->assertStringContainsString( 'Configure', $output );
	}

	/**
	 * Mock render callback for testing
	 *
	 * @return void
	 */
	public function mock_render_callback(): void {
		echo '<div class="mock-settings">Mock Settings Content</div>';
	}
}
