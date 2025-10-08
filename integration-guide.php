<?php
/**
 * Integration Guide for Silver Assist Settings Hub
 *
 * This file demonstrates how to integrate the Settings Hub into your WordPress plugin.
 * Copy the relevant sections to your plugin's main file or initialization class.
 *
 * @package SilverAssist\SettingsHub
 * @since 1.0.0
 */

declare(strict_types=1);

use SilverAssist\SettingsHub\SettingsHub;

/**
 * Example Plugin Class
 *
 * This example shows how to integrate Settings Hub into your plugin.
 */
final class My_Silver_Assist_Plugin {
	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	private const VERSION = '1.0.0';

	/**
	 * Plugin slug for settings.
	 *
	 * @var string
	 */
	private const PLUGIN_SLUG = 'my-silver-assist-plugin';

	/**
	 * Initialize the plugin.
	 *
	 * @return void
	 */
	public function init(): void {
		// Register WordPress hooks.
		add_action( 'init', [ $this, 'register_with_settings_hub' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	/**
	 * Register this plugin with the Settings Hub.
	 *
	 * @return void
	 */
	public function register_with_settings_hub(): void {
		// Check if Settings Hub is available.
		if ( ! class_exists( SettingsHub::class ) ) {
			// Fallback: Register standalone settings page if hub is not available.
			add_action( 'admin_menu', [ $this, 'register_standalone_settings' ] );
			return;
		}

		// Get the hub instance.
		$hub = SettingsHub::get_instance();

		// Register our plugin with the hub.
		$hub->register_plugin(
			self::PLUGIN_SLUG,
			'My Plugin',
			[ $this, 'render_settings_page' ],
			[
				'description' => 'This is my awesome Silver Assist plugin that does something great.',
				'version'     => self::VERSION,
				'tab_title'   => 'My Plugin', // Optional: custom tab title.
			]
		);

		// Optional: Disable tabs if you prefer.
		// $hub->enable_tabs( false );
	}

	/**
	 * Fallback: Register standalone settings page if hub is not available.
	 *
	 * This ensures your plugin still works even if the Settings Hub package
	 * is not installed or activated.
	 *
	 * @return void
	 */
	public function register_standalone_settings(): void {
		add_options_page(
			'My Plugin Settings',
			'My Plugin',
			'manage_options',
			self::PLUGIN_SLUG,
			[ $this, 'render_settings_page' ]
		);
	}

	/**
	 * Register plugin settings with WordPress Settings API.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		// Register a setting.
		register_setting(
			self::PLUGIN_SLUG,
			'my_plugin_api_key',
			[
				'type'              => 'string',
				'description'       => 'API Key for My Plugin',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			]
		);

		register_setting(
			self::PLUGIN_SLUG,
			'my_plugin_enabled',
			[
				'type'              => 'boolean',
				'description'       => 'Enable My Plugin functionality',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => true,
			]
		);

		// Add a settings section.
		add_settings_section(
			'my_plugin_main_section',
			'Main Settings',
			[ $this, 'render_section_description' ],
			self::PLUGIN_SLUG
		);

		// Add settings fields.
		add_settings_field(
			'my_plugin_enabled',
			'Enable Plugin',
			[ $this, 'render_enabled_field' ],
			self::PLUGIN_SLUG,
			'my_plugin_main_section'
		);

		add_settings_field(
			'my_plugin_api_key',
			'API Key',
			[ $this, 'render_api_key_field' ],
			self::PLUGIN_SLUG,
			'my_plugin_main_section'
		);
	}

	/**
	 * Render settings page content.
	 *
	 * This is the callback registered with Settings Hub.
	 * It should render your plugin's settings form.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'my-plugin' ) );
		}

		// Get current settings values.
		$api_key = get_option( 'my_plugin_api_key', '' );
		$enabled = get_option( 'my_plugin_enabled', true );

		?>
		<div class="wrap silverassist-settings-page">
			<!-- Optional: Add a description or intro text -->
			<div class="silverassist-settings-intro">
				<p class="description">
					<?php esc_html_e( 'Configure your plugin settings below. These settings control how the plugin behaves on your site.', 'my-plugin' ); ?>
				</p>
			</div>

			<?php settings_errors(); ?>

			<form method="post" action="options.php">
				<?php
				// Output security fields for the registered setting.
				settings_fields( self::PLUGIN_SLUG );

				// Output setting sections and their fields.
				do_settings_sections( self::PLUGIN_SLUG );

				// Output save settings button.
				submit_button( 'Save Settings' );
				?>
			</form>

			<!-- Optional: Add additional content like documentation or status -->
			<div class="silverassist-settings-footer">
				<hr>
				<h3><?php esc_html_e( 'Plugin Information', 'my-plugin' ); ?></h3>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Version', 'my-plugin' ); ?></th>
						<td><code><?php echo esc_html( self::VERSION ); ?></code></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Status', 'my-plugin' ); ?></th>
						<td>
							<?php if ( $enabled ) : ?>
								<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
								<?php esc_html_e( 'Active', 'my-plugin' ); ?>
							<?php else : ?>
								<span class="dashicons dashicons-dismiss" style="color: #dc3232;"></span>
								<?php esc_html_e( 'Inactive', 'my-plugin' ); ?>
							<?php endif; ?>
						</td>
					</tr>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Render section description.
	 *
	 * @return void
	 */
	public function render_section_description(): void {
		echo '<p>' . esc_html__( 'Configure the main settings for this plugin.', 'my-plugin' ) . '</p>';
	}

	/**
	 * Render enabled field.
	 *
	 * @return void
	 */
	public function render_enabled_field(): void {
		$enabled = get_option( 'my_plugin_enabled', true );
		?>
		<label>
			<input 
				type="checkbox" 
				name="my_plugin_enabled" 
				value="1" 
				<?php checked( $enabled, true ); ?>
			>
			<?php esc_html_e( 'Enable plugin functionality', 'my-plugin' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'Uncheck to temporarily disable the plugin without deactivating it.', 'my-plugin' ); ?>
		</p>
		<?php
	}

	/**
	 * Render API key field.
	 *
	 * @return void
	 */
	public function render_api_key_field(): void {
		$api_key = get_option( 'my_plugin_api_key', '' );
		?>
		<input 
			type="text" 
			name="my_plugin_api_key" 
			value="<?php echo esc_attr( $api_key ); ?>" 
			class="regular-text"
			placeholder="<?php esc_attr_e( 'Enter your API key', 'my-plugin' ); ?>"
		>
		<p class="description">
			<?php esc_html_e( 'Enter your API key from the service provider.', 'my-plugin' ); ?>
		</p>
		<?php
	}
}

/**
 * Bootstrap Example
 *
 * This shows how to initialize your plugin with Settings Hub integration.
 */
function initialize_my_plugin(): void {
	$plugin = new My_Silver_Assist_Plugin();
	$plugin->init();
}

// Hook into plugins_loaded to ensure all dependencies are loaded.
add_action( 'plugins_loaded', 'initialize_my_plugin' );

/**
 * Alternative: Integration in existing plugin class
 *
 * If you already have a plugin class, just add the registration method:
 */

/*
class Existing_Plugin {
    public function __construct() {
        add_action( 'init', [ $this, 'register_with_hub' ] );
    }

    public function register_with_hub(): void {
        if ( ! class_exists( SettingsHub::class ) ) {
            return; // Hub not available, use your existing settings page.
        }

        $hub = SettingsHub::get_instance();
        $hub->register_plugin(
            'my-plugin-slug',
            'My Plugin Name',
            [ $this, 'render_settings' ],
            [
                'description' => 'Plugin description',
                'version'     => '1.0.0',
            ]
        );
    }

    public function render_settings(): void {
        // Your existing settings rendering code here.
    }
}
*/

/**
 * Tips for Integration
 *
 * 1. **Fallback Support**: Always check if SettingsHub class exists before using it.
 *    This ensures your plugin works even if the hub package is not installed.
 *
 * 2. **Remove Old Menu Registration**: If migrating from standalone settings,
 *    remove your old add_options_page() call to avoid duplicate menu items.
 *
 * 3. **Use Unique Slugs**: Ensure your plugin slug is unique across all
 *    Silver Assist plugins to avoid conflicts.
 *
 * 4. **Version Display**: Include your plugin version in the registration args
 *    so it appears on the dashboard card.
 *
 * 5. **Tab Title**: You can use a shorter title for the tab if your plugin
 *    name is long. Use the 'tab_title' argument.
 *
 * 6. **Settings API**: Continue using WordPress Settings API (register_setting,
 *    add_settings_section, etc.) as normal. The hub only handles menu creation.
 *
 * 7. **Translations**: Use your plugin's text domain for all strings in your
 *    settings page. The hub uses 'silverassist-settings-hub' for its own strings.
 */
