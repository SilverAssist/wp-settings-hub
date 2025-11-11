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
}
