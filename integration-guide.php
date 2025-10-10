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
				'actions'     => [
					// Example: Add a "Check Updates" button (if using wp-github-updater).
					[
						'label' => 'Check Updates',
						'url'   => admin_url( 'update-core.php' ),
						'class' => 'button',
					],
					// Example: Add custom action with callback.
					[
						'label'    => 'Clear Cache',
						'callback' => function() {
							echo 'console.log("Cache cleared!");';
						},
						'class'    => 'button',
					],
				],
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
 *
 * 8. **Custom Actions**: You can add custom action buttons to the dashboard cards
 *    using the 'actions' parameter. This is useful for features like "Check Updates".
 */

/**
 * =============================================================================
 * EXAMPLE: Integration with wp-github-updater Package
 * =============================================================================
 *
 * This example shows how to add a "Check Updates" button when using the
 * silverassist/wp-github-updater package for automatic plugin updates.
 *
 * CRITICAL: This implementation includes proper WordPress cache synchronization
 * to ensure the Updates page (update-core.php) displays updates correctly.
 *
 * Understanding the Cache System:
 * WordPress uses a two-tier cache system for plugin updates:
 * 1. Plugin-specific cache: Transient '{plugin}_version_check' (GitHub API data)
 * 2. WordPress system cache: Site transient 'update_plugins' (update-core.php data)
 *
 * Both caches must be cleared and WordPress must be told to check for updates,
 * otherwise update-core.php will show stale information.
 */

/*
use SilverAssist\SettingsHub\SettingsHub;
use SilverAssist\WpGithubUpdater\Updater;
use SilverAssist\WpGithubUpdater\UpdaterConfig;

class My_Plugin_With_Updates {
	private const VERSION = '1.0.0';
	private const PLUGIN_SLUG = 'my-plugin';
	private const GITHUB_REPO = 'SilverAssist/my-plugin';

	private ?Updater $updater = null;

	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init_updater' ) );
		add_action( 'plugins_loaded', array( $this, 'register_with_hub' ) );
		
		// Register AJAX handler for manual update checks
		add_action( 'wp_ajax_my_plugin_check_updates', array( $this, 'ajax_check_updates' ) );
	}

	public function init_updater(): void {
		if ( ! class_exists( Updater::class ) ) {
			return;
		}

		$config = new UpdaterConfig(
			__FILE__,
			self::GITHUB_REPO,
			array(
				'text_domain' => 'my-plugin',
				'ajax_action' => 'my_plugin_check_updates',
				'ajax_nonce'  => 'my_plugin_updates_nonce',
			)
		);

		$this->updater = new Updater( $config );
	}

	public function register_with_hub(): void {
		if ( ! class_exists( SettingsHub::class ) ) {
			return;
		}

		$hub = SettingsHub::get_instance();

		// Prepare actions array
		$actions = array();

		// Add "Check Updates" button if updater is available
		if ( null !== $this->updater ) {
			$actions[] = array(
				'label'    => __( 'Check Updates', 'my-plugin' ),
				'callback' => array( $this, 'render_check_updates_script' ),
				'class'    => 'button',
			);
		}

		$hub->register_plugin(
			self::PLUGIN_SLUG,
			__( 'My Plugin', 'my-plugin' ),
			array( $this, 'render_settings' ),
			array(
				'description' => __( 'My plugin with automatic GitHub updates', 'my-plugin' ),
				'version'     => self::VERSION,
				'actions'     => $actions,
			)
		);
	}

	/**
	 * AJAX handler for manual update checks.
	 * 
	 * CRITICAL: This method implements proper cache synchronization to ensure
	 * the WordPress Updates page displays current information.
	 * 
	 * Cache Clearing Flow:
	 * 1. Clear plugin version cache (GitHub API data)
	 * 2. Clear WordPress update cache (update-core.php data) ← CRITICAL!
	 * 3. Force WordPress to check for updates NOW (triggers API call)
	 * 4. Check if update is available
	 * 5. Return result
	 * 
	 * Without steps 2 and 3, update-core.php will show stale data even though
	 * the admin notice shows an update is available.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_check_updates(): void {
		// 1. Validate nonce
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'my_plugin_updates_nonce' ) ) {
			wp_send_json_error( array(
				'message' => __( 'Security validation failed', 'my-plugin' ),
			) );
			return;
		}

		// 2. Check user capability
		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_send_json_error( array(
				'message' => __( 'Insufficient permissions', 'my-plugin' ),
			) );
			return;
		}

		// 3. Get updater instance
		if ( null === $this->updater ) {
			wp_send_json_error( array(
				'message' => __( 'Updater not available', 'my-plugin' ),
			) );
			return;
		}

		try {
			// CRITICAL CACHE SYNCHRONIZATION STEPS:
			
			// Step 1: Clear plugin version cache (GitHub API cache)
			$transient_key = dirname( plugin_basename( __FILE__ ) ) . '_version_check';
			delete_transient( $transient_key );

			// Step 2: Clear WordPress update cache (CRITICAL!)
			// Without this, update-core.php will show stale data
			delete_site_transient( 'update_plugins' );

			// Step 3: Force WordPress to check for updates NOW
			// This triggers the 'pre_set_site_transient_update_plugins' hook
			// which wp-github-updater listens to and queries GitHub API
			wp_update_plugins();

			// 4. Get update status
			$update_available = $this->updater->isUpdateAvailable();
			$current_version  = $this->updater->getCurrentVersion();
			$latest_version   = $this->updater->getLatestVersion();

			// 5. Return success response
			wp_send_json_success( array(
				'update_available' => $update_available,
				'current_version'  => $current_version,
				'latest_version'   => $latest_version,
				'message'          => $update_available
					? __( 'Update available!', 'my-plugin' )
					: __( "You're up to date!", 'my-plugin' ),
			) );

		} catch ( \Exception $e ) {
			// Log error for debugging
			error_log( 'My Plugin Update Check Error: ' . $e->getMessage() );

			wp_send_json_error( array(
				'message' => __( 'Error checking for updates', 'my-plugin' ),
			) );
		}
	}

	public function render_check_updates_script( string $plugin_slug ): void {
		// Output JavaScript to check for updates via AJAX
		?>
		var button = event.target;
		var originalText = button.textContent;
		button.disabled = true;
		button.textContent = '<?php esc_html_e( 'Checking...', 'my-plugin' ); ?>';

		jQuery.post(ajaxurl, {
			action: 'my_plugin_check_updates',
			nonce: '<?php echo esc_js( wp_create_nonce( 'my_plugin_updates_nonce' ) ); ?>'
		}).done(function(response) {
			if (response.success) {
				if (response.data.update_available) {
					button.textContent = '<?php esc_html_e( 'Update Available!', 'my-plugin' ); ?>';
					button.classList.add('button-primary');
					// Redirect to updates page after 1.5 seconds
					setTimeout(function() {
						window.location.href = '<?php echo esc_js( admin_url( 'update-core.php' ) ); ?>';
					}, 1500);
				} else {
					button.textContent = '<?php esc_html_e( 'Up to Date', 'my-plugin' ); ?>';
					// Reset button after 2 seconds
					setTimeout(function() {
						button.textContent = originalText;
						button.disabled = false;
					}, 2000);
				}
			} else {
				button.textContent = '<?php esc_html_e( 'Error', 'my-plugin' ); ?>';
				console.error('Update check failed:', response.data ? response.data.message : 'Unknown error');
				// Reset button after 2 seconds
				setTimeout(function() {
					button.textContent = originalText;
					button.disabled = false;
				}, 2000);
			}
		}).fail(function(xhr, status, error) {
			button.textContent = '<?php esc_html_e( 'Error', 'my-plugin' ); ?>';
			console.error('AJAX failed:', error);
			// Reset button after 2 seconds
			setTimeout(function() {
				button.textContent = originalText;
				button.disabled = false;
			}, 2000);
		});
		<?php
	}

	public function render_settings(): void {
		// Your settings page rendering code
	}
}
*/

/**
 * Common Mistakes to Avoid:
 *
 * ❌ WRONG: Only clearing plugin cache
 *    delete_transient( $transient_key );
 *    // Problem: WordPress core still has stale data
 *
 * ❌ WRONG: Clearing both caches but not forcing update
 *    delete_transient( $transient_key );
 *    delete_site_transient( 'update_plugins' );
 *    // Problem: Caches are empty but WordPress hasn't checked for updates
 *
 * ✅ CORRECT: Clear both caches AND force WordPress to check
 *    delete_transient( $transient_key );
 *    delete_site_transient( 'update_plugins' );
 *    wp_update_plugins();  // This is the critical step!
 *
 * Testing Your Implementation:
 * 1. Click "Check Updates" button
 * 2. Wait for success message
 * 3. Navigate to Dashboard > Updates (update-core.php)
 * 4. Your plugin should appear in the list if an update is available
 * 5. You should be able to click "Update Now" immediately
 */
