# Settings Hub Integration Guide

**Complete implementation guide for integrating `silverassist/wp-settings-hub` into WordPress plugins.**

Based on real-world implementations and tested patterns from Silver Assist plugins.

---

## Table of Contents

1. [Overview](#overview)
2. [Installation](#installation)
3. [Basic Integration Pattern](#basic-integration-pattern)
4. [Common Integration Errors](#common-integration-errors)
5. [Complete Implementation Examples](#complete-implementation-examples)
6. [Update Button Implementation](#update-button-implementation)
7. [Asset Loading Configuration](#asset-loading-configuration)
8. [Security & Permissions](#security--permissions)
9. [Testing Checklist](#testing-checklist)
10. [Best Practices](#best-practices)

---

## Overview

The `silverassist/wp-settings-hub` package provides a centralized admin menu system for Silver Assist plugins. Instead of each plugin creating its own top-level menu, all plugins register as submenus under a unified "Silver Assist" menu.

### Benefits

- **Centralized Navigation**: All Silver Assist plugins in one menu
- **Consistent UX**: Unified admin interface across plugins
- **Reduced Clutter**: Single top-level menu instead of multiple
- **Dashboard Actions**: Optional action buttons on the plugin's dashboard card (a link or a callback), for example for an update check
- **Tab Navigation**: Optional tabs for cross-plugin navigation

### Requirements

- **Package**: `silverassist/wp-settings-hub` ^1.2 (`plugin_file` support arrived in 1.2.0)
- **WordPress**: 6.5+
- **PHP**: 8.2+ (strict types required)
- **Capability**: `manage_options` by default; a plugin can pass its own `capability` (1.3.0+)

---

## Installation

### Step 1: Add Composer Dependency

The package is installed through a Composer `vcs` repository and a GitHub token. Follow the [README installation steps](https://github.com/SilverAssist/wp-settings-hub/blob/main/README.md#installation), then require it:

```bash
composer require silverassist/wp-settings-hub:^1.2
```

### Step 2: Verify Autoloading

Ensure your `composer.json` includes proper PSR-4 autoloading:

```json
{
    "name": "silverassist/your-plugin",
    "type": "wordpress-plugin",
    "autoload": {
        "psr-4": {
            "SilverAssist\\YourPlugin\\": "src/"
        }
    },
    "require": {
        "php": ">=8.2",
        "silverassist/wp-settings-hub": "^1.2"
    },
    "require-dev": {
        "squizlabs/php_codesniffer": "^3.7",
        "wp-coding-standards/wpcs": "^3.0",
        "phpstan/phpstan": "^1.10"
    }
}
```

### Step 3: Regenerate Autoloader

```bash
composer dump-autoload
```

### Step 4: Load Composer in Plugin

In your main plugin file:

```php
<?php
/**
 * Plugin Name: Your Plugin Name
 * Description: Your plugin description
 * Version: 1.0.0
 * Requires at least: 6.5
 * Requires PHP: 8.2
 * Author: Silver Assist
 * Text Domain: your-plugin
 * Domain Path: /languages
 * License: PolyForm-Noncommercial-1.0.0
 */

declare(strict_types=1);

namespace SilverAssist\YourPlugin;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Main plugin file, passed to the hub as 'plugin_file'
define( 'YOUR_PLUGIN_FILE', __FILE__ );

// Load Composer autoloader
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Initialize plugin
add_action( 'plugins_loaded', [ Plugin::class, 'get_instance' ] );
```

---

## Basic Integration Pattern

### Minimal Working Example

This is the **absolute minimum code** needed to integrate with Settings Hub:

```php
<?php
declare(strict_types=1);

namespace SilverAssist\YourPlugin;

use SilverAssist\SettingsHub\SettingsHub;

class Plugin {
    private static ?Plugin $instance = null;

    public static function get_instance(): Plugin {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // CRITICAL: Use priority 4 to register BEFORE Settings Hub (priority 5)
        add_action( 'admin_menu', [ $this, 'register_with_hub' ], 4 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
    }

    public function register_with_hub(): void {
        // Check if Settings Hub is available
        if ( ! class_exists( SettingsHub::class ) ) {
            // Fallback: Register standalone menu
            $this->register_standalone_menu();
            return;
        }

        $hub = SettingsHub::get_instance();

        $hub->register_plugin(
            'your-plugin',                                    // Slug (must be unique)
            __( 'Your Plugin', 'your-plugin' ),              // Name
            [ $this, 'render_settings_page' ],               // Callback
            [
                'description' => __( 'Plugin description', 'your-plugin' ),
                'plugin_file' => YOUR_PLUGIN_FILE,               // Main plugin file (see Step 4)
                'version'     => '1.0.0',
                // 'capability' => 'edit_posts',             // Optional (1.3.0+), defaults to manage_options
            ]
        );
    }

    private function register_standalone_menu(): void {
        add_options_page(
            __( 'Your Plugin', 'your-plugin' ),
            __( 'Your Plugin', 'your-plugin' ),
            'manage_options',
            'your-plugin',
            [ $this, 'render_settings_page' ]
        );
    }

    public function render_settings_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Your Plugin Settings', 'your-plugin' ); ?></h1>
            <!-- Your settings form here -->
        </div>
        <?php
    }

    public function enqueue_admin_assets( string $hook_suffix ): void {
        // Define possible hook suffixes (standalone and hub contexts)
        $allowed_hooks = [
            'settings_page_your-plugin',       // Standalone menu
            'silver-assist_page_your-plugin',  // Settings Hub submenu
        ];

        if ( ! in_array( $hook_suffix, $allowed_hooks, true ) ) {
            return;
        }

        // Enqueue your admin assets
        wp_enqueue_style( 'your-plugin-admin', plugins_url( 'assets/css/admin.css', __FILE__ ), [], '1.0.0' );
        wp_enqueue_script( 'your-plugin-admin', plugins_url( 'assets/js/admin.js', __FILE__ ), [ 'jquery' ], '1.0.0', true );
    }

    // Prevent cloning and unserialization
    private function __clone() {}
    public function __wakeup() {
        throw new \Exception( 'Cannot unserialize singleton' );
    }
}
```

---

## Common Integration Errors

Based on real-world implementations, these are the **most common errors** and their solutions:

### Error 1: Submenu Not Appearing in Settings Hub

**Symptom**: Plugin registers successfully but doesn't appear in "Silver Assist" menu.

**Cause**: Plugin registers at default priority (10), same as or after Settings Hub (priority 5), causing race condition.

**Solution**: Use priority **4** for `admin_menu` hook.

```php
// ❌ WRONG - Default priority (10)
add_action( 'admin_menu', [ $this, 'register_with_hub' ] );

// ✅ CORRECT - Priority 4 (before Settings Hub)
add_action( 'admin_menu', [ $this, 'register_with_hub' ], 4 );
```

**Why this happens**: WordPress executes hooks in priority order. If your plugin registers at priority 10, Settings Hub has already created its menu at priority 5, and your submenu registration fails.

---

### Error 2: "Sorry, you are not allowed to access this page" (403)

**Symptom**: A Silver Assist page answers with a 403, or a form saves with a 403 while its page loads.

**Cause 1: the user lacks the page's capability.** Plugin pages require `manage_options` unless the plugin registers with a `capability` (1.3.0+). WordPress answers 403 to anyone whose role does not hold it.

```php
$hub->register_plugin(
    'your-plugin',
    __( 'Your Plugin', 'your-plugin' ),
    [ $this, 'render_settings_page' ],
    [
        'capability' => 'edit_posts', // Editors can open this page.
    ]
);
```

**Cause 2: the Dashboard needs its own capability.** `admin.php?page=silver-assist` requires `manage_options` (change it with the `silverassist_settings_hub_dashboard_capability` filter). A user who only holds a plugin's capability is refused the Dashboard and should open the plugin page; the Dashboard tab is hidden for them.

**Cause 3: saving goes through `options.php`.** When the settings page saves with the Settings API, WordPress checks `manage_options` for the save request, whatever capability the page uses. Lower it for your option group:

```php
add_filter(
    'option_page_capability_your_option_group',
    static fn(): string => 'edit_posts'
);
```

---

### Error 3: CSS/JavaScript Not Loading

**Symptom**: Styles and scripts don't load when plugin accessed via Settings Hub.

**Cause**: Hook suffix is different between standalone (`settings_page_*`) and Hub (`silver-assist_page_*`) contexts.

**Solution**: Check for **both** hook suffixes in asset enqueue function.

```php
// ❌ WRONG - Only checks standalone context
public function enqueue_admin_assets( string $hook_suffix ): void {
    if ( $hook_suffix !== 'settings_page_your-plugin' ) {
        return;
    }
    // Assets won't load when accessed via Settings Hub!
}

// ✅ CORRECT - Checks both contexts
public function enqueue_admin_assets( string $hook_suffix ): void {
    $allowed_hooks = [
        'settings_page_your-plugin',       // Standalone fallback
        'silver-assist_page_your-plugin',  // Settings Hub submenu
    ];

    if ( ! in_array( $hook_suffix, $allowed_hooks, true ) ) {
        return;
    }

    // Assets load in both contexts ✅
    wp_enqueue_style( ... );
    wp_enqueue_script( ... );
}
```

**Debug tip**: Add this to see the actual hook suffix:
```php
error_log( "Hook suffix: " . $hook_suffix );
```

---

### Error 4: Update Button Not Working

**Symptom**: Clicking "Check Updates" button does nothing, no JavaScript executes.

**Cause**: Settings Hub expects action callbacks to **echo** JavaScript code, not **return** it.

**Solution**: Change return type to `void` and use `echo`.

```php
// ❌ WRONG - Returns JavaScript (doesn't execute)
public function render_update_check( string $plugin_slug = '' ): string {
    wp_enqueue_script( 'your-plugin-update', ... );
    wp_localize_script( ... );
    
    return "checkForUpdates(); return false;";  // ❌ Not executed
}

// ✅ CORRECT - Echoes JavaScript (executes immediately)
public function render_update_check( string $plugin_slug = '' ): void {
    wp_enqueue_script( 'your-plugin-update', ... );
    wp_localize_script( ... );
    
    echo "checkForUpdates(); return false;";  // ✅ Executed
}
```

**Why this matters**: Settings Hub renders action buttons with `onclick` attributes. When the button is clicked, WordPress evaluates the callback's output. If you return the string, it goes to the output buffer but doesn't execute. If you echo it, it becomes part of the `onclick` attribute value.

---

## Complete Implementation Examples

### Example 1: Simple Settings Page

Complete working example for a basic plugin:

```php
<?php
declare(strict_types=1);

namespace SilverAssist\SimplePlugin;

use SilverAssist\SettingsHub\SettingsHub;

class Simple_Plugin {
    private const VERSION = '1.0.0';
    private const SLUG = 'simple-plugin';

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_with_hub' ], 4 );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function register_with_hub(): void {
        if ( ! class_exists( SettingsHub::class ) ) {
            add_options_page(
                __( 'Simple Plugin', 'simple-plugin' ),
                __( 'Simple Plugin', 'simple-plugin' ),
                'manage_options',
                self::SLUG,
                [ $this, 'render_page' ]
            );
            return;
        }

        $hub = SettingsHub::get_instance();
        $hub->register_plugin(
            self::SLUG,
            __( 'Simple Plugin', 'simple-plugin' ),
            [ $this, 'render_page' ],
            [
                'description' => __( 'A simple plugin example', 'simple-plugin' ),
                'plugin_file' => SIMPLE_PLUGIN_FILE, // Main plugin file, defined with define( 'SIMPLE_PLUGIN_FILE', __FILE__ ) in the main file
                'version'     => self::VERSION,
            ]
        );
    }

    public function register_settings(): void {
        register_setting(
            self::SLUG . '_options',
            self::SLUG . '_option_name',
            [
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => '',
            ]
        );

        add_settings_section(
            self::SLUG . '_section',
            __( 'General Settings', 'simple-plugin' ),
            null,
            self::SLUG
        );

        add_settings_field(
            self::SLUG . '_field',
            __( 'Option Name', 'simple-plugin' ),
            [ $this, 'render_field' ],
            self::SLUG,
            self::SLUG . '_section'
        );
    }

    public function render_field(): void {
        $value = get_option( self::SLUG . '_option_name', '' );
        printf(
            '<input type="text" name="%s" value="%s" class="regular-text" />',
            esc_attr( self::SLUG . '_option_name' ),
            esc_attr( $value )
        );
    }

    public function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Show save message
        if ( isset( $_GET['settings-updated'] ) ) {
            add_settings_error(
                self::SLUG . '_messages',
                self::SLUG . '_message',
                __( 'Settings saved successfully!', 'simple-plugin' ),
                'success'
            );
        }

        settings_errors( self::SLUG . '_messages' );
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( self::SLUG . '_options' );
                do_settings_sections( self::SLUG );
                submit_button( __( 'Save Settings', 'simple-plugin' ) );
                ?>
            </form>
        </div>
        <?php
    }

    public function enqueue_assets( string $hook_suffix ): void {
        $allowed = [
            'settings_page_' . self::SLUG,
            'silver-assist_page_' . self::SLUG,
        ];

        if ( ! in_array( $hook_suffix, $allowed, true ) ) {
            return;
        }

        wp_enqueue_style(
            self::SLUG . '-admin',
            plugins_url( 'assets/css/admin.css', dirname( __FILE__ ) ),
            [],
            self::VERSION
        );
    }
}
```

### Example 2: Plugin with Update Button

Complete example with wp-github-updater integration:

```php
<?php
declare(strict_types=1);

namespace SilverAssist\AdvancedPlugin;

use SilverAssist\SettingsHub\SettingsHub;
use SilverAssist\WpGithubUpdater\Updater;
use SilverAssist\WpGithubUpdater\UpdaterConfig;

class Advanced_Plugin {
    private const VERSION = '1.0.0';
    private const SLUG = 'advanced-plugin';
    private const GITHUB_REPO = 'SilverAssist/advanced-plugin';

    private ?Updater $updater = null;

    public function __construct() {
        add_action( 'plugins_loaded', [ $this, 'init_updater' ] );
        add_action( 'admin_menu', [ $this, 'register_with_hub' ], 4 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_ajax_advanced_plugin_check_updates', [ $this, 'ajax_check_updates' ] );
    }

    public function init_updater(): void {
        if ( ! class_exists( UpdaterConfig::class ) ) {
            return;
        }

        $config = new UpdaterConfig(
            __FILE__,
            self::GITHUB_REPO,
            [
                'text_domain' => 'advanced-plugin',
                'ajax_action' => 'advanced_plugin_check_updates',
                'ajax_nonce'  => 'advanced_plugin_updates_nonce',
            ]
        );

        $this->updater = new Updater( $config );
    }

    public function register_with_hub(): void {
        if ( ! class_exists( SettingsHub::class ) ) {
            add_options_page(
                __( 'Advanced Plugin', 'advanced-plugin' ),
                __( 'Advanced Plugin', 'advanced-plugin' ),
                'manage_options',
                self::SLUG,
                [ $this, 'render_page' ]
            );
            return;
        }

        $hub = SettingsHub::get_instance();

        // Prepare actions array
        $actions = [];
        if ( null !== $this->updater ) {
            $actions[] = [
                'label'    => __( 'Check Updates', 'advanced-plugin' ),
                'callback' => [ $this, 'render_update_check' ],
                'class'    => 'button',
            ];
        }

        $hub->register_plugin(
            self::SLUG,
            __( 'Advanced Plugin', 'advanced-plugin' ),
            [ $this, 'render_page' ],
            [
                'description' => __( 'Advanced plugin with GitHub updates', 'advanced-plugin' ),
                'plugin_file' => ADVANCED_PLUGIN_FILE, // Main plugin file, defined with define( 'ADVANCED_PLUGIN_FILE', __FILE__ ) in the main file
                'version'     => self::VERSION,
                'actions'     => $actions,
            ]
        );
    }

    /**
     * Render update check button script
     * ⚠️ CRITICAL: Must ECHO JavaScript, not RETURN it
     */
    public function render_update_check( string $plugin_slug = '' ): void {
        wp_enqueue_script(
            self::SLUG . '-update-check',
            plugins_url( 'assets/js/update-check.js', dirname( __FILE__ ) ),
            [ 'jquery' ],
            self::VERSION,
            true
        );

        wp_localize_script(
            self::SLUG . '-update-check',
            'advancedPluginUpdateData',
            [
                'ajaxurl'   => admin_url( 'admin-ajax.php' ),
                'nonce'     => wp_create_nonce( 'advanced_plugin_updates_nonce' ),
                'updateUrl' => admin_url( 'update-core.php' ),
                'strings'   => [
                    'checking'  => __( 'Checking for updates...', 'advanced-plugin' ),
                    'available' => __( 'Update available! Redirecting...', 'advanced-plugin' ),
                    'upToDate'  => __( 'Plugin is up to date!', 'advanced-plugin' ),
                    'error'     => __( 'Error checking for updates', 'advanced-plugin' ),
                ],
            ]
        );

        // ⚠️ CRITICAL: Echo, don't return
        echo 'advancedPluginCheckUpdates(); return false;';
    }

    /**
     * AJAX handler for update checks
     * Implements proper cache synchronization
     */
    public function ajax_check_updates(): void {
        // Verify nonce
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'advanced_plugin_updates_nonce' ) ) {
            wp_send_json_error(
                [
                    'message' => __( 'Security check failed', 'advanced-plugin' ),
                ]
            );
            return;
        }

        // Check capability
        if ( ! current_user_can( 'update_plugins' ) ) {
            wp_send_json_error(
                [
                    'message' => __( 'Insufficient permissions', 'advanced-plugin' ),
                ]
            );
            return;
        }

        // Check updater availability
        if ( null === $this->updater ) {
            wp_send_json_error(
                [
                    'message' => __( 'Updater not available', 'advanced-plugin' ),
                ]
            );
            return;
        }

        try {
            // CRITICAL: Clear both plugin and WordPress caches
            $transient_key = dirname( plugin_basename( __FILE__ ) ) . '_version_check';
            delete_transient( $transient_key );
            delete_site_transient( 'update_plugins' );
            wp_update_plugins();

            // Check update status
            $update_available = $this->updater->isUpdateAvailable();
            $current_version  = $this->updater->getCurrentVersion();
            $latest_version   = $this->updater->getLatestVersion();

            wp_send_json_success(
                [
                    'update_available' => $update_available,
                    'current_version'  => $current_version,
                    'latest_version'   => $latest_version,
                    'message'          => $update_available
                        ? __( 'Update available!', 'advanced-plugin' )
                        : __( "You're up to date!", 'advanced-plugin' ),
                ]
            );
        } catch ( \Exception $e ) {
            error_log( 'Advanced Plugin Update Check Error: ' . $e->getMessage() );
            wp_send_json_error(
                [
                    'message' => __( 'Error checking for updates', 'advanced-plugin' ),
                ]
            );
        }
    }

    public function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <p><?php esc_html_e( 'Advanced plugin settings page', 'advanced-plugin' ); ?></p>
        </div>
        <?php
    }

    public function enqueue_assets( string $hook_suffix ): void {
        $allowed = [
            'settings_page_' . self::SLUG,
            'silver-assist_page_' . self::SLUG,
        ];

        if ( ! in_array( $hook_suffix, $allowed, true ) ) {
            return;
        }

        wp_enqueue_style(
            self::SLUG . '-admin',
            plugins_url( 'assets/css/admin.css', dirname( __FILE__ ) ),
            [],
            self::VERSION
        );
    }
}
```

---

## Update Button Implementation

### JavaScript Implementation (`assets/js/update-check.js`)

Complete, production-ready update check script with WordPress notifications:

```javascript
/**
 * Update Check Functionality
 * Displays WordPress-style admin notices
 * 
 * @since 1.0.0
 */
(function($) {
    "use strict";

    /**
     * Display WordPress admin notice
     * 
     * @param {string} message Notice message
     * @param {string} type Notice type (success, error, warning, info)
     */
    const showAdminNotice = function(message, type) {
        type = type || "info";
        
        // Remove existing notices
        $(".notice.your-plugin-notice").remove();

        // Build notice HTML
        const noticeClass = "notice notice-" + type + " is-dismissible your-plugin-notice";
        const noticeHtml = 
            "<div class=\"" + noticeClass + "\">" +
                "<p><strong>" + message + "</strong></p>" +
                "<button type=\"button\" class=\"notice-dismiss\">" +
                    "<span class=\"screen-reader-text\">Dismiss this notice.</span>" +
                "</button>" +
            "</div>";

        // Insert after page title
        const $notice = $(noticeHtml);
        $("h1").first().after($notice);

        // Handle dismiss
        $notice.find(".notice-dismiss").on("click", function() {
            $notice.fadeOut(300, function() {
                $(this).remove();
            });
        });

        // Auto-dismiss success/info after 5 seconds
        if (type === "success" || type === "info") {
            setTimeout(function() {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
        }
    };

    /**
     * Check for plugin updates
     * Called by Settings Hub action button
     */
    window.yourPluginCheckUpdates = function() {
        showAdminNotice(yourPluginUpdateData.strings.checking, "info");

        $.ajax({
            url: yourPluginUpdateData.ajaxurl,
            type: "POST",
            data: {
                action: "your_plugin_check_updates",
                nonce: yourPluginUpdateData.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    if (response.data.update_available) {
                        showAdminNotice(yourPluginUpdateData.strings.available, "success");
                        setTimeout(function() {
                            window.location.href = yourPluginUpdateData.updateUrl;
                        }, 2000);
                    } else {
                        showAdminNotice(yourPluginUpdateData.strings.upToDate, "success");
                    }
                } else {
                    const errorMsg = response.data && response.data.message
                        ? response.data.message
                        : yourPluginUpdateData.strings.error;
                    showAdminNotice(errorMsg, "error");
                }
            },
            error: function(xhr, status, error) {
                showAdminNotice(yourPluginUpdateData.strings.error, "error");
                console.error("Update check failed:", error);
            }
        });
    };

})(jQuery);
```

---

## Asset Loading Configuration

### Recommended Directory Structure

```text
your-plugin/
├── assets/
│   ├── css/
│   │   ├── admin.css
│   │   └── admin.min.css
│   └── js/
│       ├── admin.js
│       ├── admin.min.js
│       ├── update-check.js
│       └── update-check.min.js
├── languages/
│   └── your-plugin.pot
├── src/
│   ├── Admin/
│   │   └── Settings.php
│   └── Core/
│       └── Plugin.php
├── vendor/
├── composer.json
├── phpcs.xml
├── phpstan.neon
└── your-plugin.php
```

### CSS Best Practices

Use CSS custom properties for consistency:

```css
/**
 * Admin Styles
 * Follows WordPress admin design system
 */
:root {
    --plugin-primary: #0073aa;
    --plugin-success: #46b450;
    --plugin-error: #dc3232;
    --plugin-warning: #ffb900;
    --plugin-spacing: 15px;
}

.your-plugin-notice {
    border-left-width: 4px;
    padding: var(--plugin-spacing);
    margin: 20px 0;
}

.your-plugin-notice.notice-success {
    border-left-color: var(--plugin-success);
}

.your-plugin-notice.notice-error {
    border-left-color: var(--plugin-error);
}
```

---

## Security & Permissions

### Capability Validation

Always validate user capabilities:

```php
/**
 * Validate admin access
 *
 * @return bool
 */
private function validate_admin_access(): bool {
    if ( ! is_user_logged_in() ) {
        return false;
    }

    if ( ! current_user_can( 'manage_options' ) ) {
        return false;
    }

    return true;
}

/**
 * Render settings page with capability check
 */
public function render_settings_page(): void {
    if ( ! $this->validate_admin_access() ) {
        wp_die(
            esc_html__( 'You do not have permission to access this page.', 'your-plugin' ),
            esc_html__( 'Permission Denied', 'your-plugin' ),
            [ 'response' => 403 ]
        );
    }

    // Render page content
}
```

---

## Testing Checklist

### Pre-Deployment Testing

- [ ] **Settings Hub Active**
  - [ ] Submenu appears under "Silver Assist" menu
  - [ ] Plugin name and description display correctly
  - [ ] Version number shows on dashboard card
  - [ ] Settings page loads without errors

- [ ] **Settings Hub Inactive**
  - [ ] Fallback standalone menu appears
  - [ ] Settings page accessible from WordPress Settings menu
  - [ ] All functionality works identically

- [ ] **Admin Access**
  - [ ] Page loads without 403 errors
  - [ ] Correct capability checks on page load
  - [ ] Capability checks on form submission
  - [ ] Unauthorized users see permission error

- [ ] **Asset Loading**
  - [ ] CSS loads correctly in both contexts
  - [ ] JavaScript executes properly
  - [ ] No 404 errors for assets in browser console
  - [ ] Minified versions load in production (if applicable)

- [ ] **Update Button** (if applicable)
  - [ ] Button appears on dashboard card
  - [ ] Click triggers AJAX request
  - [ ] Success shows WordPress notice
  - [ ] Error shows appropriate message
  - [ ] Redirects to update-core.php on update available
  - [ ] Cache synchronization works correctly

- [ ] **Form Functionality**
  - [ ] Form submission works correctly
  - [ ] Data saved to database
  - [ ] Success message displays
  - [ ] Data retrieved and displayed correctly
  - [ ] Input sanitization works

- [ ] **Security**
  - [ ] Nonce verification works
  - [ ] Capability checks prevent unauthorized access
  - [ ] SQL injection prevention (use prepared statements)
  - [ ] XSS prevention (proper escaping)
  - [ ] CSRF protection (nonces)

- [ ] **Code Quality**
  - [ ] PHPCS passes (WordPress Coding Standards)
  - [ ] PHPStan passes (level 8)
  - [ ] No PHP warnings or notices
  - [ ] No JavaScript console errors
  - [ ] Strict types declared in all files

### WordPress Environment Testing

- [ ] WordPress 6.5
- [ ] WordPress 6.6
- [ ] WordPress latest
- [ ] PHP 8.2
- [ ] PHP 8.3
- [ ] PHP 8.4

### Multi-Plugin Testing

- [ ] Single plugin installation
- [ ] Multiple Silver Assist plugins installed
- [ ] Hub enables/disables correctly
- [ ] No plugin conflicts
- [ ] Theme compatibility (Twenty Twenty-Four, etc.)

---

## Best Practices

### Critical Requirements (Must Follow)

1. **Hook Priority 4**: ALWAYS use priority 4 for `admin_menu` hook
2. **Capability Parameter**: optional (1.3.0+). Omit it for administrator-only pages and pass `capability` only to open a page to a lower role
3. **Multiple Hook Suffixes**: ALWAYS check for both `settings_page_*` and `silver-assist_page_*`
4. **Echo vs Return**: ALWAYS echo JavaScript in action callbacks, never return
5. **Class Existence**: ALWAYS check `class_exists( SettingsHub::class )` before using
6. **Fallback Menu**: ALWAYS provide standalone menu for when Hub unavailable
7. **Security**: ALWAYS verify nonces and capabilities for form/AJAX submissions
8. **Sanitization**: ALWAYS sanitize user input
9. **Escaping**: ALWAYS escape output
10. **i18n**: ALWAYS use WordPress i18n functions for user-facing strings

### Code Organization

**Recommended Structure:**

```text
✅ One main Plugin class (singleton pattern)
✅ Separate Admin class for admin functionality
✅ Private methods for internal logic
✅ Public methods for WordPress callbacks
✅ Group related functionality
✅ Document all public methods with PHPDoc
✅ Use strict types in all files
✅ Follow WordPress Coding Standards
```

### Version Management

```php
// Define version constant in main plugin file
define( 'YOUR_PLUGIN_VERSION', '1.0.0' );
define( 'YOUR_PLUGIN_FILE', __FILE__ );
define( 'YOUR_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'YOUR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Use constants consistently
$hub->register_plugin( 'slug', 'Name', $callback, [
    'version'     => YOUR_PLUGIN_VERSION,  // ✅ Use constant
    'plugin_file' => YOUR_PLUGIN_FILE,
] );

wp_enqueue_script( 'script', $url, [], YOUR_PLUGIN_VERSION, true );
wp_enqueue_style( 'style', $url, [], YOUR_PLUGIN_VERSION );
```

### Performance Optimization

```php
/**
 * Load assets only when needed
 */
public function enqueue_assets( string $hook_suffix ): void {
    // Early return if not our page
    $allowed = [
        'settings_page_your-plugin',
        'silver-assist_page_your-plugin',
    ];

    if ( ! in_array( $hook_suffix, $allowed, true ) ) {
        return;
    }

    // Load minified versions in production
    $suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

    wp_enqueue_style(
        'your-plugin-admin',
        YOUR_PLUGIN_URL . "assets/css/admin{$suffix}.css",
        [],
        YOUR_PLUGIN_VERSION
    );

    wp_enqueue_script(
        'your-plugin-admin',
        YOUR_PLUGIN_URL . "assets/js/admin{$suffix}.js",
        [ 'jquery' ],
        YOUR_PLUGIN_VERSION,
        true
    );
}
```

---

## Additional Resources

### Documentation

- **Settings Hub Package**: https://github.com/SilverAssist/wp-settings-hub
- **WordPress Plugin API**: https://developer.wordpress.org/plugins/
- **WordPress Coding Standards**: https://developer.wordpress.org/coding-standards/
- **WordPress i18n**: https://developer.wordpress.org/plugins/internationalization/
- **WordPress Settings API**: https://developer.wordpress.org/plugins/settings/

### Support

For issues or questions:
- Review this guide for common errors and solutions
- Read the [README](https://github.com/SilverAssist/wp-settings-hub/blob/main/README.md) for the API reference
- Review [integration-guide.php](https://github.com/SilverAssist/wp-settings-hub/blob/main/integration-guide.php) for additional examples
- Contact Silver Assist development team

---

**License**: PolyForm-Noncommercial-1.0.0
