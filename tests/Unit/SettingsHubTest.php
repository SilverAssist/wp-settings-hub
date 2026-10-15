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
	 * Set up test environment: rendering tests run as an administrator.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->login_as( 'administrator' );
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
		$hub = $this->hub();

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
		$hub = $this->hub();

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
		$hub = $this->hub();

		$this->assertFalse( $hub->is_plugin_registered( 'nonexistent-plugin' ), 'Should return false for unregistered plugin' );
	}

	/**
	 * Test get_parent_slug.
	 */
	public function test_get_parent_slug(): void {
		$hub = $this->hub();

		$this->assertSame( 'silver-assist', $hub->get_parent_slug(), 'Parent slug should be silver-assist' );
	}

	/**
	 * Test enable_tabs.
	 */
	public function test_enable_tabs(): void {
		$hub = $this->hub();

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
		$hub = $this->hub();

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
		$hub = $this->hub();

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
		$hub = $this->hub();
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
		$hub = $this->hub();
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
		$hub = $this->hub();

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
		$hub = $this->hub();

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
		$hub = $this->hub();
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
	 * Test enqueue_styles registers the stylesheet with the resolved URL and the package version.
	 */
	public function test_enqueue_styles_on_silver_assist_page(): void {
		$fixture = $this->make_vendor_fixture();

		$this->hub()->register_plugin(
			'test-plugin',
			'Test Plugin',
			static function (): void {
				echo 'Settings';
			},
			array( 'plugin_file' => $fixture['file'] )
		);

		$this->hub()->enqueue_styles( 'toplevel_page_silver-assist' );

		$this->assertTrue( wp_style_is( 'silverassist-settings-hub', 'enqueued' ), 'CSS should be enqueued on Silver Assist pages' );

		$style = wp_styles()->registered['silverassist-settings-hub'];
		$this->assertSame( $fixture['css_url'], $style->src, 'The style src should be the vendor URL derived from plugin_file' );
		$this->assertSame(
			( new \ReflectionClassConstant( SettingsHub::class, 'PACKAGE_VERSION' ) )->getValue(),
			$style->ver,
			'The style ver should be the package version'
		);
	}

	/**
	 * Test plugin_file is not stored in plugin data.
	 */
	public function test_plugin_file_not_stored_in_plugins(): void {
		$hub = $this->hub();

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

		// Verify plugin_file was captured internally.
		$captured_file = $this->captured_plugin_file();

		$this->assertSame( $plugin_file_path, $captured_file, 'plugin_file should be captured internally for asset URL resolution' );
	}

	/**
	 * Test an action with a URL renders an escaped link carrying the given class.
	 */
	public function test_action_with_url_renders_an_escaped_link_with_its_class(): void {
		$output = $this->render_action(
			array(
				'label' => 'Docs',
				'url'   => 'https://example.com/docs?a=1&b=2',
				'class' => 'button button-secondary',
			)
		);

		$this->assertStringContainsString( 'href="https://example.com/docs?a=1&#038;b=2"', $output, 'The ampersand in the URL should be escaped by esc_url' );
		$this->assertStringNotContainsString( 'a=1&b=2', $output, 'The raw ampersand should not be printed' );
		$this->assertStringContainsString( 'class="button button-secondary"', $output, 'The link should carry the given class' );
		$this->assertStringContainsString( 'Docs', $output, 'The link should show the label' );
		$this->assertStringNotContainsString( '<button', $output, 'A URL action should not render a button' );
	}

	/**
	 * Test the action class falls back to "button".
	 */
	public function test_action_class_defaults_to_button(): void {
		$output = $this->render_action(
			array(
				'label' => 'Docs',
				'url'   => 'https://example.com/docs',
			)
		);

		$this->assertStringContainsString( 'class="button"', $output, 'The default class should be button' );
	}

	/**
	 * Test an action with a callback renders a button and an inline script wrapping the callback output.
	 */
	public function test_action_with_callback_renders_a_button_and_inline_script(): void {
		$received = array();
		$output   = $this->render_action(
			array(
				'label'    => 'Run Now',
				'class'    => 'button button-secondary',
				'callback' => static function ( string $slug ) use ( &$received ): void {
					$received[] = $slug;
					echo 'CALLBACK_OUTPUT_' . $slug;
				},
			),
			'my-plugin'
		);

		$button_id = 'sa-action-' . sanitize_key( 'my-plugin-Run Now' );

		$this->assertSame( 'sa-action-my-plugin-runnow', $button_id, 'Sanity check of the expected id' );
		$this->assertStringContainsString( '<button', $output, 'A callback action should render a button' );
		$this->assertStringContainsString( 'id="' . $button_id . '"', $output, 'The button id should be sa-action- plus the sanitized slug and label' );
		$this->assertStringContainsString( 'data-plugin="my-plugin"', $output, 'The button should carry the plugin slug' );
		$this->assertStringContainsString( 'class="button button-secondary"', $output, 'The button should carry the given class' );
		$this->assertStringContainsString( "document.getElementById('" . $button_id . "')", $output, 'The script should target the button id' );
		$this->assertSame( array( 'my-plugin' ), $received, 'The callback should run once and receive the plugin slug' );
		$this->assertMatchesRegularExpression(
			'/addEventListener\(\'click\', function\(\) \{\s*CALLBACK_OUTPUT_my-plugin\s*\}\);/',
			$output,
			'The callback output should sit inside the click listener'
		);
	}

	/**
	 * Test the URL wins when an action gives both a URL and a callback.
	 */
	public function test_action_url_wins_when_url_and_callback_are_both_given(): void {
		$called = false;
		$output = $this->render_action(
			array(
				'label'    => 'Both',
				'url'      => 'https://example.com/both',
				'callback' => static function () use ( &$called ): void {
					$called = true;
					echo 'CALLBACK_OUTPUT';
				},
			)
		);

		$this->assertStringContainsString( 'href="https://example.com/both"', $output, 'The URL link should be rendered' );
		$this->assertFalse( $called, 'The callback should not run when a URL is given' );
		$this->assertStringNotContainsString( '<button', $output, 'No button should be rendered' );
		$this->assertStringNotContainsString( '<script', $output, 'No script should be rendered' );
		$this->assertStringNotContainsString( 'CALLBACK_OUTPUT', $output, 'The callback output should not appear' );
	}

	/**
	 * Test an action without a label prints nothing and never runs its callback.
	 */
	public function test_action_without_label_is_skipped(): void {
		$called = false;
		$action = static function () use ( &$called ): void {
			$called = true;
		};

		$this->assertSame( '', $this->render_action( array( 'url' => 'https://example.com' ) ), 'A missing label should print nothing' );
		$this->assertSame(
			'',
			$this->render_action(
				array(
					'label' => '',
					'url'   => 'https://example.com',
				)
			),
			'An empty label should print nothing'
		);
		$this->assertSame( '', $this->render_action( array( 'callback' => $action ) ), 'A callback without label should print nothing' );
		$this->assertFalse( $called, 'The callback should not run without a label' );
	}

	/**
	 * Test an action with a label but neither URL nor callback prints nothing.
	 */
	public function test_action_with_neither_url_nor_callback_is_skipped(): void {
		$this->assertSame( '', $this->render_action( array( 'label' => 'Orphan' ) ), 'An action without URL or callback should print nothing' );
		$this->assertSame(
			'',
			$this->render_action(
				array(
					'label'    => 'Orphan',
					'callback' => 'not_a_real_function_name',
				)
			),
			'A non-callable callback should print nothing'
		);
	}

	/**
	 * Test the plugin actions hook fires once per card with the slug and plugin data, inside the actions element.
	 */
	public function test_plugin_actions_hook_fires_once_per_card_with_slug_and_plugin(): void {
		$calls = array();
		add_action(
			'silverassist_settings_hub_plugin_actions',
			static function ( $slug, $plugin ) use ( &$calls ): void {
				$calls[] = array( $slug, $plugin );
				echo 'LISTENER_' . $slug;
			},
			10,
			2
		);

		$this->register( 'first', 'First Plugin', array( 'version' => '1.0.0' ) );
		$this->register( 'second', 'Second Plugin' );

		$output  = $this->render( array( $this->hub(), 'render_dashboard' ) );
		$plugins = $this->hub()->get_plugins();

		$this->assertCount( 2, $calls, 'The hook should fire once per card' );
		$this->assertSame( 'first', $calls[0][0], 'The first call should receive the first slug' );
		$this->assertSame( $plugins['first'], $calls[0][1], 'The first call should receive the first plugin data' );
		$this->assertSame( 'second', $calls[1][0], 'The second call should receive the second slug' );
		$this->assertSame( $plugins['second'], $calls[1][1], 'The second call should receive the second plugin data' );

		$this->assertSame( 2, preg_match_all( '#<div class="plugin-actions">(.*?)</div>#s', $output, $matches ), 'There should be one actions element per card' );
		$this->assertStringContainsString( 'LISTENER_first', $matches[1][0], 'The listener output should sit inside the first actions element' );
		$this->assertStringNotContainsString( 'LISTENER_second', $matches[1][0], 'The second listener output should not leak into the first card' );
		$this->assertStringContainsString( 'LISTENER_second', $matches[1][1], 'The listener output should sit inside the second actions element' );
	}

	/**
	 * Test re-registering a slug replaces its data and keeps the card count and position.
	 */
	public function test_registering_the_same_slug_replaces_its_data_without_duplicating_the_card_or_changing_position(): void {
		$this->hub()->enable_tabs( false );
		$this->register( 'alpha', 'Alpha Plugin' );
		$this->register( 'beta', 'Beta Original' );
		$this->register( 'gamma', 'Gamma Plugin' );

		$this->register( 'beta', 'Beta Renamed', array( 'version' => '9.9.9' ) );

		$plugins = $this->hub()->get_plugins();
		$this->assertSame( array( 'alpha', 'beta', 'gamma' ), array_keys( $plugins ), 'The key order should be unchanged' );
		$this->assertSame( 'Beta Renamed', $plugins['beta']['name'], 'The data should be replaced' );
		$this->assertSame( '9.9.9', $plugins['beta']['version'] ?? null, 'The new arguments should be stored' );

		$output = $this->render( array( $this->hub(), 'render_dashboard' ) );
		$this->assertSame( 3, substr_count( $output, 'class="silverassist-plugin-card"' ), 'There should still be three cards' );
		$this->assertSame( 1, substr_count( $output, 'Beta Renamed' ), 'The new name should be rendered once' );
		$this->assertStringNotContainsString( 'Beta Original', $output, 'The old name should be gone' );

		$alpha_at = strpos( $output, 'Alpha Plugin' );
		$beta_at  = strpos( $output, 'Beta Renamed' );
		$gamma_at = strpos( $output, 'Gamma Plugin' );
		$this->assertIsInt( $alpha_at );
		$this->assertIsInt( $beta_at );
		$this->assertIsInt( $gamma_at );
		$this->assertLessThan( $beta_at, $alpha_at, 'Alpha should render before Beta' );
		$this->assertLessThan( $gamma_at, $beta_at, 'Beta should render before Gamma' );
	}

	/**
	 * Test only the first registering plugin supplies plugin_file, and it is never stored in plugin data.
	 */
	public function test_plugin_file_is_captured_only_from_the_first_registering_plugin(): void {
		$this->register( 'first', 'First Plugin', array( 'plugin_file' => '/first/plugin.php' ) );
		$this->register( 'second', 'Second Plugin', array( 'plugin_file' => '/second/plugin.php' ) );

		$this->assertSame( '/first/plugin.php', $this->captured_plugin_file(), 'The second plugin file should not replace the first' );

		foreach ( $this->hub()->get_plugins() as $plugin ) {
			$this->assertArrayNotHasKey( 'plugin_file', $plugin, 'plugin_file should not be stored in plugin data' );
			$this->assertNotContains( '/first/plugin.php', $plugin, 'The first path should not leak into plugin data' );
			$this->assertNotContains( '/second/plugin.php', $plugin, 'The second path should not leak into plugin data' );
		}
	}

	/**
	 * Test untrusted plugin metadata is escaped in the card, the tabs and the plugin page.
	 */
	public function test_untrusted_plugin_metadata_is_escaped_everywhere(): void {
		$payload = '<script>alert(1)</script>"q"';
		$escaped = '&lt;script&gt;alert(1)&lt;/script&gt;&quot;q&quot;';
		$slug    = 'evil' . $payload;

		$this->hub()->register_plugin(
			$slug,
			'Name' . $payload,
			static function (): void {
				echo 'BODY';
			},
			array(
				'description' => 'Desc' . $payload,
				'version'     => '1.0' . $payload,
				'tab_title'   => 'Tab' . $payload,
				'actions'     => array(
					array(
						'label'    => 'Act' . $payload,
						'class'    => 'cls' . $payload,
						'callback' => static function (): void {},
					),
				),
			)
		);

		$plugin    = $this->hub()->get_plugins()[ $slug ];
		$dashboard = $this->render( array( $this->hub(), 'render_dashboard' ) );
		$tabs      = $this->render(
			function (): void {
				$this->call_hub_method( 'render_tabs_navigation', array( '' ) );
			}
		);
		$page      = $this->render(
			function () use ( $plugin ): void {
				$this->call_hub_method( 'render_plugin_page', array( $plugin ) );
			}
		);

		$outputs = array(
			'dashboard' => $dashboard,
			'tabs'      => $tabs,
			'page'      => $page,
		);
		foreach ( $outputs as $where => $output ) {
			$this->assertStringNotContainsString( '<script>', $output, "No raw script tag should appear in the {$where}" );
			$this->assertStringNotContainsString( 'alert(1)</script>', $output, "No raw payload should appear in the {$where}" );
		}

		$this->assertStringContainsString( 'Name' . $escaped, $dashboard, 'The card name should be escaped' );
		$this->assertStringContainsString( 'Desc' . $escaped, $dashboard, 'The card description should be escaped' );
		$this->assertStringContainsString( 'v1.0' . $escaped, $dashboard, 'The card version should be escaped' );
		$this->assertStringContainsString( 'Act' . $escaped, $dashboard, 'The action label should be escaped' );
		$this->assertStringContainsString( 'class="cls' . $escaped . '"', $dashboard, 'The action class should be escaped' );
		$this->assertStringContainsString( 'data-plugin="evil' . $escaped . '"', $dashboard, 'The slug should be escaped in the action button' );
		$this->assertStringContainsString( 'Tab' . $escaped, $dashboard, 'The tab title should be escaped on the dashboard tabs' );

		$this->assertStringContainsString( 'Tab' . $escaped, $tabs, 'The tab title should be escaped in the tabs navigation' );

		$this->assertStringContainsString( 'Name' . $escaped, $page, 'The page title should be escaped' );
		$this->assertStringContainsString( 'v1.0' . $escaped, $page, 'The page version should be escaped' );
		$this->assertStringContainsString( 'Tab' . $escaped, $page, 'The tab title should be escaped on the plugin page' );
		$this->assertStringContainsString( 'BODY', $page, 'The plugin callback should still render' );
	}

	/**
	 * Test the card omits the description and the version badge when they are not given.
	 */
	public function test_dashboard_without_description_or_version_omits_those_elements(): void {
		$this->register( 'bare', 'Bare Plugin' );

		$output = $this->render( array( $this->hub(), 'render_dashboard' ) );

		$this->assertStringContainsString( 'Bare Plugin', $output, 'The name should render' );
		$this->assertStringContainsString( 'Configure', $output, 'The Configure button should render' );
		$this->assertStringNotContainsString( 'card-description', $output, 'No description paragraph should render' );
		$this->assertStringNotContainsString( 'silverassist-version-badge', $output, 'No version badge should render' );
	}

	/**
	 * Test several plugins render one card each, with optional parts only where given.
	 */
	public function test_dashboard_renders_several_plugins_at_once(): void {
		$this->hub()->enable_tabs( false );
		$this->register(
			'one',
			'Plugin One',
			array(
				'description' => 'Description one',
				'version'     => '2.5.3',
			)
		);
		$this->register( 'two', 'Plugin Two' );
		$this->register(
			'three',
			'Plugin Three',
			array(
				'description' => 'Description three',
				'version'     => '0.1.0',
			)
		);

		$output = $this->render( array( $this->hub(), 'render_dashboard' ) );
		$cards  = explode( 'class="silverassist-plugin-card"', $output );
		array_shift( $cards );

		$this->assertCount( 3, $cards, 'There should be one card per plugin' );
		$this->assertStringContainsString( 'Plugin One', $cards[0], 'The first card should hold the first plugin' );
		$this->assertStringContainsString( 'Plugin Two', $cards[1], 'The second card should hold the second plugin' );
		$this->assertStringContainsString( 'Plugin Three', $cards[2], 'The third card should hold the third plugin' );

		$this->assertStringContainsString( 'v2.5.3', $cards[0], 'The version should render with the v prefix' );
		$this->assertStringContainsString( 'Description one', $cards[0], 'The first description should render' );
		$this->assertStringNotContainsString( 'silverassist-version-badge', $cards[1], 'The card without version should have no badge' );
		$this->assertStringNotContainsString( 'card-description', $cards[1], 'The card without description should have no paragraph' );
		$this->assertStringContainsString( 'v0.1.0', $cards[2], 'The third version should render' );
		$this->assertStringContainsString( 'Description three', $cards[2], 'The third description should render' );
		$this->assertSame( 2, substr_count( $output, 'silverassist-version-badge' ), 'Only two cards have a version badge' );
		$this->assertSame( 2, substr_count( $output, 'card-description' ), 'Only two cards have a description' );
	}

	/**
	 * Test each card links to its plugin page with the primary button classes.
	 */
	public function test_configure_link_points_to_the_plugin_page(): void {
		$this->register( 'alpha', 'Alpha Plugin' );
		$this->register( 'beta-two', 'Beta Plugin' );

		$output = $this->render( array( $this->hub(), 'render_dashboard' ) );

		$this->assertSame( 2, preg_match_all( '#<a href="([^"]*)" class="button button-primary">\s*Configure#', $output, $matches ), 'Each card should have a Configure link' );
		$this->assertSame(
			array( admin_url( 'admin.php?page=alpha' ), admin_url( 'admin.php?page=beta-two' ) ),
			$matches[1],
			'The links should point to each plugin page in order'
		);
	}

	/**
	 * Test get_plugins keeps the registration order rather than sorting.
	 */
	public function test_get_plugins_keeps_registration_order_for_many_plugins(): void {
		$order = array( 'mango', 'apple', 'zebra', 'banana', 'kiwi' );
		foreach ( $order as $slug ) {
			$this->register( $slug, ucfirst( $slug ) );
		}

		$this->assertSame( $order, array_keys( $this->hub()->get_plugins() ), 'The order should be exactly the registration order' );
	}

	/**
	 * Register a plugin whose settings page echoes a marker.
	 *
	 * @param string               $slug Plugin slug.
	 * @param string               $name Plugin name.
	 * @param array<string, mixed> $args Optional register_plugin() arguments.
	 */
	private function register( string $slug, string $name, array $args = array() ): void {
		$this->hub()->register_plugin(
			$slug,
			$name,
			static function () use ( $slug ): void {
				echo 'Settings of ' . $slug;
			},
			$args
		);
	}

	/**
	 * Render one action button through the private render_action_button().
	 *
	 * @param array<string, mixed> $action Action data.
	 * @param string               $slug   Plugin slug.
	 *
	 * @return string The printed markup.
	 */
	private function render_action( array $action, string $slug = 'some-plugin' ): string {
		return $this->render(
			function () use ( $action, $slug ): void {
				$this->call_hub_method( 'render_action_button', array( $action, $slug ) );
			}
		);
	}

	/**
	 * Read the hub's private plugin_file property.
	 *
	 * @return mixed The captured plugin file.
	 */
	private function captured_plugin_file(): mixed {
		return ( new \ReflectionProperty( SettingsHub::class, 'plugin_file' ) )->getValue( $this->hub() );
	}
}
