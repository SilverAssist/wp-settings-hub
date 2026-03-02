<?php
/**
 * Tests for SettingsHub class.
 *
 * @package SilverAssist\SettingsHub\Tests
 */

declare(strict_types=1);

namespace SilverAssist\SettingsHub\Tests\Unit;

use SilverAssist\SettingsHub\Tests\TestCase;
use SilverAssist\SettingsHub\SettingsHub;

/**
 * Test suite for SettingsHub.
 *
 * @covers \SilverAssist\SettingsHub\SettingsHub
 */
final class SettingsHubTest extends TestCase {
	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {
		parent::setUp();
		// WordPress functions are now available via WP_UnitTestCase or Brain Monkey trait.
	}

	/**
	 * Tear down test environment.
	 */
	protected function tearDown(): void {
		// Reset singleton instance using reflection to clean state between tests.
		$reflection = new \ReflectionClass( SettingsHub::class );
		$instance   = $reflection->getProperty( 'instance' );
		$instance->setAccessible( true );
		$instance->setValue( null, null );

		parent::tearDown();
	}

	/**
	 * Test singleton pattern.
	 */
	public function test_singleton(): void {
		$instance1 = SettingsHub::get_instance();
		$instance2 = SettingsHub::get_instance();

		$this->assertSame( $instance1, $instance2, 'Should return the same instance' );
	}

	/**
	 * Test plugin registration.
	 */
	public function test_register_plugin(): void {
		$hub = SettingsHub::get_instance();

		$callback = static function (): void {
			echo 'Plugin settings';
		};

		$hub->register_plugin(
			'test-plugin',
			'Test Plugin',
			$callback,
			array(
				'description' => 'Test plugin description',
				'version'     => '1.0.0',
			)
		);

		$this->assertTrue( $hub->is_plugin_registered( 'test-plugin' ), 'Plugin should be registered' );

		$plugins = $hub->get_plugins();
		$this->assertArrayHasKey( 'test-plugin', $plugins, 'Plugins array should contain test-plugin' );
		$plugin = $plugins['test-plugin'];
		$this->assertSame( 'Test Plugin', $plugin['name'], 'Plugin name should match' );
		$this->assertSame( 'test-plugin', $plugin['slug'], 'Plugin slug should match' );
		$this->assertSame( $callback, $plugin['callback'], 'Plugin callback should match' );
		$this->assertArrayHasKey( 'description', $plugin, 'Plugin should have description' );
		if ( isset( $plugin['description'] ) ) {
			$this->assertSame( 'Test plugin description', $plugin['description'], 'Plugin description should match' );
		}
		$this->assertArrayHasKey( 'version', $plugin, 'Plugin should have version' );
		if ( isset( $plugin['version'] ) ) {
			$this->assertSame( '1.0.0', $plugin['version'], 'Plugin version should match' );
		}
	}

	/**
	 * Test multiple plugin registration.
	 */
	public function test_register_multiple_plugins(): void {
		$hub = SettingsHub::get_instance();

		$hub->register_plugin(
			'plugin-1',
			'Plugin 1',
			static function (): void {
				echo 'Plugin 1 settings';
			}
		);

		$hub->register_plugin(
			'plugin-2',
			'Plugin 2',
			static function (): void {
				echo 'Plugin 2 settings';
			}
		);

		$plugins = $hub->get_plugins();
		$this->assertCount( 2, $plugins, 'Should have 2 registered plugins' );
		$this->assertTrue( $hub->is_plugin_registered( 'plugin-1' ), 'Plugin 1 should be registered' );
		$this->assertTrue( $hub->is_plugin_registered( 'plugin-2' ), 'Plugin 2 should be registered' );
	}

	/**
	 * Test is_plugin_registered with unregistered plugin.
	 */
	public function test_is_plugin_registered_false(): void {
		$hub = SettingsHub::get_instance();

		$this->assertFalse( $hub->is_plugin_registered( 'nonexistent-plugin' ), 'Should return false for unregistered plugin' );
	}

	/**
	 * Test get_parent_slug.
	 */
	public function test_get_parent_slug(): void {
		$hub = SettingsHub::get_instance();

		$this->assertSame( 'silver-assist', $hub->get_parent_slug(), 'Parent slug should be silver-assist' );
	}

	/**
	 * Test enable_tabs.
	 */
	public function test_enable_tabs(): void {
		$hub = SettingsHub::get_instance();

		$this->assertTrue( $hub->is_tabs_enabled(), 'Tabs should be enabled by default' );

		$hub->enable_tabs( false );
		$this->assertFalse( $hub->is_tabs_enabled(), 'Tabs should be disabled' );

		$hub->enable_tabs( true );
		$this->assertTrue( $hub->is_tabs_enabled(), 'Tabs should be enabled' );
	}

	/**
	 * Test dashboard rendering with no plugins.
	 */
	public function test_render_dashboard_no_plugins(): void {
		$hub = SettingsHub::get_instance();

		ob_start();
		$hub->render_dashboard();
		$output = ob_get_clean();

		$this->assertNotFalse( $output, 'Output should not be false' );
		$this->assertIsString( $output, 'Output should be a string' );
		$this->assertStringContainsString( 'Silver Assist', $output, 'Should contain title' );
		$this->assertStringContainsString( 'No Silver Assist plugins have been registered yet', $output, 'Should show no plugins message' );
	}

	/**
	 * Test dashboard rendering with plugins.
	 */
	public function test_render_dashboard_with_plugins(): void {
		$hub = SettingsHub::get_instance();

		$hub->register_plugin(
			'test-plugin',
			'Test Plugin',
			static function (): void {
				echo 'Settings';
			},
			array(
				'description' => 'Test description',
				'version'     => '1.0.0',
			)
		);

		ob_start();
		$hub->render_dashboard();
		$output = ob_get_clean();

		$this->assertNotFalse( $output, 'Output should not be false' );
		$this->assertIsString( $output, 'Output should be a string' );
		$this->assertStringContainsString( 'Test Plugin', $output, 'Should contain plugin name' );
		$this->assertStringContainsString( 'Test description', $output, 'Should contain plugin description' );
		$this->assertStringContainsString( 'v1.0.0', $output, 'Should contain plugin version' );
		$this->assertStringContainsString( 'Configure', $output, 'Should contain configure button' );
	}

	/**
	 * Test tabs are rendered when enabled.
	 */
	public function test_render_dashboard_with_tabs(): void {
		$hub = SettingsHub::get_instance();
		$hub->enable_tabs( true );

		$hub->register_plugin(
			'test-plugin',
			'Test Plugin',
			static function (): void {
				echo 'Settings';
			}
		);

		ob_start();
		$hub->render_dashboard();
		$output = ob_get_clean();

		$this->assertNotFalse( $output, 'Output should not be false' );
		$this->assertIsString( $output, 'Output should be a string' );
		$this->assertStringContainsString( 'nav-tab-wrapper', $output, 'Should contain tabs navigation' );
		$this->assertStringContainsString( 'Dashboard', $output, 'Should contain Dashboard tab' );
	}

	/**
	 * Test tabs are not rendered when disabled.
	 */
	public function test_render_dashboard_without_tabs(): void {
		$hub = SettingsHub::get_instance();
		$hub->enable_tabs( false );

		$hub->register_plugin(
			'test-plugin',
			'Test Plugin',
			static function (): void {
				echo 'Settings';
			}
		);

		ob_start();
		$hub->render_dashboard();
		$output = ob_get_clean();

		$this->assertNotFalse( $output, 'Output should not be false' );
		$this->assertIsString( $output, 'Output should be a string' );
		$this->assertStringNotContainsString( 'nav-tab-wrapper', $output, 'Should not contain tabs navigation' );
	}

	/**
	 * Test dashboard uses new CSS classes instead of inline styles.
	 */
	public function test_render_dashboard_uses_css_classes(): void {
		$hub = SettingsHub::get_instance();

		$hub->register_plugin(
			'test-plugin',
			'Test Plugin',
			static function (): void {
				echo 'Settings';
			},
			array(
				'description' => 'Test description',
				'version'     => '1.0.0',
			)
		);

		ob_start();
		$hub->render_dashboard();
		$output = ob_get_clean();

		$this->assertNotFalse( $output, 'Output should not be false' );
		$this->assertIsString( $output, 'Output should be a string' );
		// Check for new CSS classes.
		$this->assertStringContainsString( 'silverassist-dashboard-description', $output, 'Should use dashboard description class' );
		$this->assertStringContainsString( 'silverassist-dashboard-grid', $output, 'Should use dashboard grid class' );
		$this->assertStringContainsString( 'silverassist-plugin-card', $output, 'Should use plugin card class' );
		$this->assertStringContainsString( 'card-header', $output, 'Should use card header class' );
		$this->assertStringContainsString( 'card-content', $output, 'Should use card content class' );
		$this->assertStringContainsString( 'silverassist-version-badge', $output, 'Should use version badge class' );
		// Verify grid has no inline style attribute.
		$this->assertDoesNotMatchRegularExpression(
			'/<div[^>]*class="[^"]*silverassist-dashboard-grid[^"]*"[^>]*\sstyle=/i',
			$output,
			'Dashboard grid should not have inline styles'
		);
	}

	/**
	 * Test empty state uses new CSS class.
	 */
	public function test_render_dashboard_empty_state_uses_css_class(): void {
		$hub = SettingsHub::get_instance();

		ob_start();
		$hub->render_dashboard();
		$output = ob_get_clean();

		$this->assertNotFalse( $output, 'Output should not be false' );
		$this->assertIsString( $output, 'Output should be a string' );
		$this->assertStringContainsString( 'silverassist-empty-state', $output, 'Should use empty state class' );
		$this->assertStringContainsString( 'dashicons-admin-plugins', $output, 'Should contain dashicon' );
	}

	/**
	 * Test tabs navigation uses new CSS class.
	 */
	public function test_render_tabs_uses_css_class(): void {
		$hub = SettingsHub::get_instance();
		$hub->enable_tabs( true );

		$hub->register_plugin(
			'test-plugin',
			'Test Plugin',
			static function (): void {
				echo 'Settings';
			}
		);

		ob_start();
		$hub->render_dashboard();
		$output = ob_get_clean();

		$this->assertNotFalse( $output, 'Output should not be false' );
		$this->assertIsString( $output, 'Output should be a string' );
		$this->assertStringContainsString( 'silverassist-hub-tabs', $output, 'Should use hub tabs class' );
		// Verify nav-tab-wrapper has no inline style attribute.
		$this->assertDoesNotMatchRegularExpression(
			'/<nav[^>]*class="[^"]*nav-tab-wrapper[^"]*"[^>]*\sstyle=/i',
			$output,
			'Tabs navigation should not have inline styles'
		);
	}

	/**
	 * Test enqueue_styles is called on Silver Assist pages.
	 */
	public function test_enqueue_styles_on_silver_assist_page(): void {
		global $wp_styles;
		$wp_styles = new \WP_Styles();

		$hub = SettingsHub::get_instance();

		// Register a plugin to trigger styles registration.
		$hub->register_plugin(
			'test-plugin',
			'Test Plugin',
			static function (): void {
				echo 'Settings';
			}
		);

		// Simulate being on a Silver Assist page.
		$hub->enqueue_styles( 'toplevel_page_silver-assist' );

		// Check that the style was enqueued.
		$this->assertTrue(
			wp_style_is( 'silverassist-settings-hub', 'enqueued' ),
			'CSS should be enqueued on Silver Assist pages'
		);
	}

	/**
	 * Test enqueue_styles does not enqueue on non-Silver Assist pages.
	 */
	public function test_enqueue_styles_not_on_other_pages(): void {
		global $wp_styles;
		$wp_styles = new \WP_Styles();

		$hub = SettingsHub::get_instance();

		// Register a plugin to trigger styles registration.
		$hub->register_plugin(
			'test-plugin',
			'Test Plugin',
			static function (): void {
				echo 'Settings';
			}
		);

		// Simulate being on a non-Silver Assist page.
		$hub->enqueue_styles( 'toplevel_page_dashboard' );

		// Check that the style was NOT enqueued.
		$this->assertFalse(
			wp_style_is( 'silverassist-settings-hub', 'enqueued' ),
			'CSS should not be enqueued on non-Silver Assist pages'
		);
	}

	/**
	 * Test plugin_file is not stored in plugin data.
	 */
	public function test_plugin_file_not_stored_in_plugins(): void {
		$hub = SettingsHub::get_instance();

		$plugin_file_path = '/path/to/plugin.php';

		$hub->register_plugin(
			'test-plugin',
			'Test Plugin',
			static function (): void {
				echo 'Settings';
			},
			array(
				'description' => 'Test description',
				'version'     => '1.0.0',
				'plugin_file' => $plugin_file_path,
			)
		);

		// Verify plugin_file is not in public plugin data.
		$plugins = $hub->get_plugins();
		$this->assertArrayNotHasKey( 'plugin_file', $plugins['test-plugin'], 'plugin_file should not be stored in plugin data' );

		// Verify plugin_file was captured internally by using reflection.
		$reflection      = new \ReflectionClass( $hub );
		$property        = $reflection->getProperty( 'plugin_file' );
		$property->setAccessible( true );
		$captured_file   = $property->getValue( $hub );

		$this->assertSame( $plugin_file_path, $captured_file, 'plugin_file should be captured internally for asset URL resolution' );
	}
}
