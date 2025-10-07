<?php
/**
 * Tests for SettingsHub class.
 *
 * @package SilverAssist\SettingsHub\Tests
 */

declare(strict_types=1);

namespace SilverAssist\SettingsHub\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use SilverAssist\SettingsHub\SettingsHub;

/**
 * Test suite for SettingsHub.
 *
 * @covers \SilverAssist\SettingsHub\SettingsHub
 */
final class SettingsHubTest extends TestCase {
	/**
	 * Set up test environment.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Mock WordPress functions.
		Functions\when( 'add_action' )->justReturn( true );
		Functions\when( 'add_options_page' )->justReturn( 'silver-assist' );
		Functions\when( 'add_submenu_page' )->justReturn( 'plugin-slug' );
		Functions\when( 'admin_url' )->returnArg();
		Functions\when( '__' )->returnArg();
		Functions\when( 'esc_html' )->returnArg();
		Functions\when( 'esc_html_e' )->alias(
			static function ( string $text ): void {
				echo $text; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		);
		Functions\when( 'esc_attr' )->returnArg();
		Functions\when( 'esc_url' )->returnArg();
	}

	/**
	 * Tear down test environment.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test singleton pattern.
	 *
	 * @return void
	 */
	public function test_singleton(): void {
		$instance1 = SettingsHub::get_instance();
		$instance2 = SettingsHub::get_instance();

		$this->assertSame( $instance1, $instance2, 'Should return the same instance' );
	}

	/**
	 * Test plugin registration.
	 *
	 * @return void
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
			[
				'description' => 'Test plugin description',
				'version'     => '1.0.0',
			]
		);

		$this->assertTrue( $hub->is_plugin_registered( 'test-plugin' ), 'Plugin should be registered' );

		$plugins = $hub->get_plugins();
		$this->assertArrayHasKey( 'test-plugin', $plugins, 'Plugins array should contain test-plugin' );
		$this->assertSame( 'Test Plugin', $plugins['test-plugin']['name'], 'Plugin name should match' );
		$this->assertSame( 'test-plugin', $plugins['test-plugin']['slug'], 'Plugin slug should match' );
		$this->assertSame( $callback, $plugins['test-plugin']['callback'], 'Plugin callback should match' );
		$this->assertSame( 'Test plugin description', $plugins['test-plugin']['description'], 'Plugin description should match' );
		$this->assertSame( '1.0.0', $plugins['test-plugin']['version'], 'Plugin version should match' );
	}

	/**
	 * Test multiple plugin registration.
	 *
	 * @return void
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
	 *
	 * @return void
	 */
	public function test_is_plugin_registered_false(): void {
		$hub = SettingsHub::get_instance();

		$this->assertFalse( $hub->is_plugin_registered( 'nonexistent-plugin' ), 'Should return false for unregistered plugin' );
	}

	/**
	 * Test get_parent_slug.
	 *
	 * @return void
	 */
	public function test_get_parent_slug(): void {
		$hub = SettingsHub::get_instance();

		$this->assertSame( 'silver-assist', $hub->get_parent_slug(), 'Parent slug should be silver-assist' );
	}

	/**
	 * Test enable_tabs.
	 *
	 * @return void
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
	 * Test register_menus adds action hook.
	 *
	 * @return void
	 */
	public function test_register_plugin_adds_action(): void {
		Functions\expect( 'add_action' )
			->once()
			->with( 'admin_menu', Monkey\Functions\when( '__' ), 5 );

		$hub = SettingsHub::get_instance();
		$hub->register_plugin(
			'test-plugin',
			'Test Plugin',
			static function (): void {
				echo 'Settings';
			}
		);
	}

	/**
	 * Test dashboard rendering with no plugins.
	 *
	 * @return void
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
	 *
	 * @return void
	 */
	public function test_render_dashboard_with_plugins(): void {
		$hub = SettingsHub::get_instance();

		$hub->register_plugin(
			'test-plugin',
			'Test Plugin',
			static function (): void {
				echo 'Settings';
			},
			[
				'description' => 'Test description',
				'version'     => '1.0.0',
			]
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
	 *
	 * @return void
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
	 *
	 * @return void
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
