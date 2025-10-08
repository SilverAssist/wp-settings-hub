# Implementation Guide - Silver Assist Settings Hub

This guide details all the steps necessary to integrate the **Settings Hub** into your Silver Assist plugins.

> **Important**: Version 1.1.0+ uses a **top-level menu** structure instead of Settings submenu. See [MIGRATION-v1.1.md](MIGRATION-v1.1.md) if upgrading from v1.0.0.

## 📋 Table of Contents

1. [Prerequisites](#prerequisites)
2. [Package Installation](#package-installation)
3. [Migration from Standalone Settings](#migration-from-standalone-settings)
4. [Implementation from Scratch](#implementation-from-scratch)
5. [Examples by Plugin Type](#examples-by-plugin-type)
6. [Best Practices](#best-practices)
7. [Troubleshooting](#troubleshooting)

---

## 📦 Prerequisites

### Required Software
- **PHP**: 8.3 or higher
- **WordPress**: 6.5 or higher
- **Composer**: For dependency management

### Verify Requirements
```bash
# Check PHP version
php -v

# Verify Composer is installed
composer --version
```

---

## 🚀 Package Installation

### Step 1: Add the Package

In your plugin's root directory, run:

```bash
composer require silverassist/wp-settings-hub
```

### Step 2: Verify Installation

Verify that the package was installed correctly:

```bash
composer show silverassist/wp-settings-hub
```

You should see something like:
```
name     : silverassist/wp-settings-hub
version  : 1.0.0
type     : library
license  : PolyForm-Noncommercial-1.0.0
```

### Step 3: Update .gitignore (if necessary)

Make sure your `.gitignore` includes:
```
/vendor/
composer.lock  # Optional: include in plugin projects
```

---

## 🔄 Migration from Standalone Settings

If your plugin **already has** an independent settings page, follow these steps to migrate to the Settings Hub **without losing functionality**.

### Current Architecture (Before)

Your plugin probably has something like:

```php
// In your main file or Plugin class
class My_Plugin {
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_settings_page' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    public function register_settings_page() {
        add_options_page(
            'My Plugin Settings',
            'My Plugin',
            'manage_options',
            'my-plugin-settings',
            [ $this, 'render_settings_page' ]
        );
    }

    public function register_settings() {
        // Register settings with WordPress Settings API
        register_setting( 'my_plugin_options', 'my_plugin_api_key' );
        // ... more settings
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'my_plugin_options' );
                do_settings_sections( 'my_plugin_options' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}
```

### Step 1: Import the SettingsHub

At the beginning of your main file or class:

```php
use SilverAssist\SettingsHub\SettingsHub;
```

### Step 2: Modify the Constructor

Replace the `admin_menu` hook with one to register with the hub:

```php
class My_Plugin {
    public function __construct() {
        // BEFORE: add_action( 'admin_menu', [ $this, 'register_settings_page' ] );
        // AFTER:
        add_action( 'plugins_loaded', [ $this, 'register_with_settings_hub' ] );
        
        // Keep this hook, settings still use WordPress Settings API
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    public function register_with_settings_hub() {
        // Check if the Settings Hub is available
        if ( ! class_exists( SettingsHub::class ) ) {
            // Fallback: use standalone page if hub is not available
            add_action( 'admin_menu', [ $this, 'register_settings_page_standalone' ] );
            return;
        }

        // Register with the Settings Hub
        $hub = SettingsHub::get_instance();
        $hub->register_plugin(
            'my-plugin-settings',           // Unique slug
            'My Plugin',                    // Display name
            [ $this, 'render_settings_page' ], // Your existing callback
            [
                'description' => 'Short description of your plugin',
                'version'     => '1.0.0',   // Your plugin version
                'icon_url'    => plugin_dir_url( __FILE__ ) . 'assets/icon.png', // Optional
                'tab_title'   => 'My Plugin', // Optional: short title for tab
            ]
        );
    }

    // Fallback method (keep your original implementation)
    public function register_settings_page_standalone() {
        add_options_page(
            'My Plugin Settings',
            'My Plugin',
            'manage_options',
            'my-plugin-settings',
            [ $this, 'render_settings_page' ]
        );
    }

    // ⚠️ IMPORTANT: DO NOT change this method
    // The Settings Hub only handles the MENU, settings remain the same
    public function register_settings() {
        register_setting( 'my_plugin_options', 'my_plugin_api_key' );
        // ... all your existing settings
    }

    // ⚠️ IMPORTANT: DO NOT change this method
    // Your settings page continues working exactly the same
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'my_plugin_options' );
                do_settings_sections( 'my_plugin_options' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}
```

### Step 3: Remove Old Registration Method (Optional)

If you no longer need the fallback, you can remove `register_settings_page_standalone()`. But it's recommended to keep it in case the hub is not available.

### Step 4: Update Plugin composer.json

Make sure your `composer.json` includes the dependency:

```json
{
    "require": {
        "php": "^8.3",
        "silverassist/wp-settings-hub": "^1.1"
    }
}
```

### Step 5: Update Autoloader in Your Main Plugin File

In your plugin's main file (e.g., `my-plugin.php`):

```php
<?php
/**
 * Plugin Name: My Plugin
 * Plugin URI: https://silverassist.com
 * Description: Description of my plugin
 * Version: 1.0.0
 * Requires at least: 6.5
 * Requires PHP: 8.3
 * Author: Silver Assist
 * License: PolyForm-Noncommercial-1.0.0
 */

declare(strict_types=1);

// Load Composer autoloader
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Initialize your plugin
require_once __DIR__ . '/includes/class-my-plugin.php';
$my_plugin = new My_Plugin();
```

### Step 6: Test the Migration

1. Run `composer install` in your plugin
2. Activate your plugin in WordPress
3. Look for **Silver Assist** in the main admin menu (top-level, with shield icon)
4. You should see your plugin in the dashboard
5. Click on your plugin's submenu item and verify that your settings page works correctly

> **Note**: The menu location has changed in v1.1.0+. It's now a top-level menu instead of under Settings.

---

## 🆕 Implementation from Scratch

If you're creating a new plugin or one without previous settings:

### Basic Structure

```php
<?php
namespace SilverAssist\MyPlugin;

use SilverAssist\SettingsHub\SettingsHub;

class Plugin {
    private const VERSION = '1.0.0';
    private const SETTINGS_SLUG = 'my-plugin';

    public function __construct() {
        add_action( 'plugins_loaded', [ $this, 'register_with_hub' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    public function register_with_hub(): void {
        if ( ! class_exists( SettingsHub::class ) ) {
            return; // Hub not available
        }

        $hub = SettingsHub::get_instance();
        $hub->register_plugin(
            self::SETTINGS_SLUG,
            'My Plugin',
            [ $this, 'render_settings' ],
            [
                'description' => 'Configuration management for My Plugin',
                'version'     => self::VERSION,
            ]
        );
    }

    public function register_settings(): void {
        register_setting(
            self::SETTINGS_SLUG,
            'my_plugin_option',
            [
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => '',
            ]
        );

        add_settings_section(
            'my_plugin_main',
            'Main Configuration',
            null,
            self::SETTINGS_SLUG
        );

        add_settings_field(
            'my_plugin_option',
            'My Option',
            [ $this, 'render_option_field' ],
            self::SETTINGS_SLUG,
            'my_plugin_main'
        );
    }

    public function render_settings(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap">
            <?php settings_errors(); ?>
            
            <form method="post" action="options.php">
                <?php
                settings_fields( self::SETTINGS_SLUG );
                do_settings_sections( self::SETTINGS_SLUG );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function render_option_field(): void {
        $value = get_option( 'my_plugin_option', '' );
        ?>
        <input 
            type="text" 
            name="my_plugin_option" 
            value="<?php echo esc_attr( $value ); ?>" 
            class="regular-text"
        >
        <?php
    }
}
```

---

## 📚 Examples by Plugin Type

### Example 1: Plugin with API Key

```php
class API_Plugin {
    public function register_with_hub(): void {
        if ( ! class_exists( SettingsHub::class ) ) {
            return;
        }

        $hub = SettingsHub::get_instance();
        $hub->register_plugin(
            'api-plugin',
            'API Plugin',
            [ $this, 'render_settings' ],
            [
                'description' => 'Configure your API key to connect external services',
                'version'     => '1.2.0',
                'icon_url'    => plugin_dir_url( __FILE__ ) . 'assets/icon.png',
            ]
        );
    }

    public function register_settings(): void {
        register_setting( 'api_plugin', 'api_plugin_key', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ] );

        register_setting( 'api_plugin', 'api_plugin_endpoint', [
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default'           => 'https://api.example.com',
        ] );
    }

    public function render_settings(): void {
        ?>
        <div class="wrap">
            <form method="post" action="options.php">
                <?php settings_fields( 'api_plugin' ); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">API Key</th>
                        <td>
                            <input 
                                type="password" 
                                name="api_plugin_key" 
                                value="<?php echo esc_attr( get_option( 'api_plugin_key' ) ); ?>"
                                class="regular-text"
                            >
                            <p class="description">Enter your service API key</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Endpoint URL</th>
                        <td>
                            <input 
                                type="url" 
                                name="api_plugin_endpoint" 
                                value="<?php echo esc_attr( get_option( 'api_plugin_endpoint' ) ); ?>"
                                class="regular-text"
                            >
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>

            <!-- Status section -->
            <hr>
            <h3>Connection Status</h3>
            <?php $this->render_connection_status(); ?>
        </div>
        <?php
    }

    private function render_connection_status(): void {
        $api_key = get_option( 'api_plugin_key' );
        
        if ( empty( $api_key ) ) {
            echo '<p>❌ You have not configured an API key</p>';
            return;
        }

        // Check connection
        $is_connected = $this->test_api_connection();
        
        if ( $is_connected ) {
            echo '<p>✅ Connected successfully</p>';
        } else {
            echo '<p>⚠️ Connection error. Check your API key</p>';
        }
    }
}
```

### Example 2: Plugin with Feature Toggle

```php
class Feature_Plugin {
    public function register_with_hub(): void {
        if ( ! class_exists( SettingsHub::class ) ) {
            return;
        }

        $hub = SettingsHub::get_instance();
        $hub->register_plugin(
            'feature-plugin',
            'Feature Plugin',
            [ $this, 'render_settings' ],
            [
                'description' => 'Enable or disable plugin functionality',
                'version'     => '2.0.0',
            ]
        );
    }

    public function register_settings(): void {
        register_setting( 'feature_plugin', 'feature_plugin_enabled', [
            'type'              => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default'           => true,
        ] );

        register_setting( 'feature_plugin', 'feature_plugin_mode', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'automatic',
        ] );
    }

    public function render_settings(): void {
        $enabled = get_option( 'feature_plugin_enabled', true );
        $mode    = get_option( 'feature_plugin_mode', 'automatic' );
        ?>
        <div class="wrap">
            <form method="post" action="options.php">
                <?php settings_fields( 'feature_plugin' ); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">Plugin Status</th>
                        <td>
                            <label>
                                <input 
                                    type="checkbox" 
                                    name="feature_plugin_enabled" 
                                    value="1"
                                    <?php checked( $enabled ); ?>
                                >
                                Enable functionality
                            </label>
                            <p class="description">
                                Disable this to pause the plugin without uninstalling it
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Operation Mode</th>
                        <td>
                            <select name="feature_plugin_mode">
                                <option value="automatic" <?php selected( $mode, 'automatic' ); ?>>
                                    Automatic
                                </option>
                                <option value="manual" <?php selected( $mode, 'manual' ); ?>>
                                    Manual
                                </option>
                                <option value="scheduled" <?php selected( $mode, 'scheduled' ); ?>>
                                    Scheduled
                                </option>
                            </select>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>

            <!-- Additional information -->
            <div class="card" style="margin-top: 20px;">
                <h3>Plugin Information</h3>
                <p><strong>Version:</strong> <?php echo esc_html( self::VERSION ); ?></p>
                <p><strong>Status:</strong> 
                    <?php echo $enabled ? '✅ Active' : '❌ Inactive'; ?>
                </p>
                <p><strong>Mode:</strong> <?php echo esc_html( ucfirst( $mode ) ); ?></p>
            </div>
        </div>
        <?php
    }
}
```

### Example 3: Plugin with Multiple Tabs (using WordPress UI)

If you need multiple sections in your settings page:

```php
class Advanced_Plugin {
    public function render_settings(): void {
        $active_tab = $_GET['tab'] ?? 'general';
        ?>
        <div class="wrap">
            <h1>Advanced Configuration</h1>

            <!-- WordPress tabs -->
            <nav class="nav-tab-wrapper wp-clearfix">
                <a 
                    href="?page=advanced-plugin&tab=general" 
                    class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>"
                >
                    General
                </a>
                <a 
                    href="?page=advanced-plugin&tab=api" 
                    class="nav-tab <?php echo $active_tab === 'api' ? 'nav-tab-active' : ''; ?>"
                >
                    API
                </a>
                <a 
                    href="?page=advanced-plugin&tab=advanced" 
                    class="nav-tab <?php echo $active_tab === 'advanced' ? 'nav-tab-active' : ''; ?>"
                >
                    Advanced
                </a>
            </nav>

            <!-- Content based on active tab -->
            <?php
            switch ( $active_tab ) {
                case 'api':
                    $this->render_api_settings();
                    break;
                case 'advanced':
                    $this->render_advanced_settings();
                    break;
                case 'general':
                default:
                    $this->render_general_settings();
                    break;
            }
            ?>
        </div>
        <?php
    }

    private function render_general_settings(): void {
        ?>
        <form method="post" action="options.php">
            <?php
            settings_fields( 'advanced_plugin_general' );
            do_settings_sections( 'advanced_plugin_general' );
            submit_button();
            ?>
        </form>
        <?php
    }

    // ... methods for other tabs
}
```

---

## ✅ Best Practices

### 1. Use Constants for Slugs

```php
class My_Plugin {
    private const SETTINGS_SLUG = 'my-plugin';
    
    public function register_with_hub(): void {
        $hub->register_plugin(
            self::SETTINGS_SLUG,  // ✅ Use constant
            // ...
        );
    }

    public function register_settings(): void {
        register_setting( self::SETTINGS_SLUG, 'option_name' );  // ✅ Same slug
    }
}
```

### 2. Provide Fallback

Always check if the hub is available:

```php
public function register_with_hub(): void {
    if ( ! class_exists( SettingsHub::class ) ) {
        // Fallback to standalone page
        add_action( 'admin_menu', [ $this, 'register_standalone' ] );
        return;
    }
    
    // Register with hub...
}
```

### 3. Include Complete Metadata

```php
$hub->register_plugin(
    'my-plugin',
    'My Plugin',
    [ $this, 'render_settings' ],
    [
        'description' => 'Clear and concise description',  // ✅ Helps users
        'version'     => self::VERSION,                    // ✅ Shows version
        'icon_url'    => $this->get_icon_url(),           // ✅ Attractive visual
        'tab_title'   => 'My Plugin',                     // ✅ Short title for tab
    ]
);
```

### 4. Sanitize All Inputs

```php
register_setting( 'my_plugin', 'api_key', [
    'sanitize_callback' => 'sanitize_text_field',  // ✅ Always sanitize
    'default'           => '',
] );
```

### 5. Use WordPress Settings API

Don't reinvent the wheel. The hub only handles the menu, use WordPress Settings API for everything else:

```php
// ✅ CORRECT
settings_fields( 'my_plugin' );
do_settings_sections( 'my_plugin' );

// ❌ INCORRECT - Don't handle settings manually
if ( isset( $_POST['my_option'] ) ) {
    update_option( 'my_option', $_POST['my_option'] );
}
```

### 6. Control Permissions

```php
public function render_settings(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'You do not have permissions', 'my-plugin' ) );
    }
    // ... rest of code
}
```

### 7. Display Plugin Status

```php
public function render_settings(): void {
    ?>
    <div class="wrap">
        <!-- Settings form -->
        <form>...</form>

        <!-- Additional info -->
        <div class="card" style="margin-top: 20px;">
            <h3>Information</h3>
            <p><strong>Version:</strong> <?php echo esc_html( self::VERSION ); ?></p>
            <p><strong>Status:</strong> <?php $this->render_status(); ?></p>
        </div>
    </div>
    <?php
}
```

---

## 🐛 Troubleshooting

### Problem 1: Plugin does not appear in dashboard

**Symptoms:**
- You don't see your plugin in the Silver Assist menu

**Solution:**
```php
// Verify you're registering on the correct hook
add_action( 'plugins_loaded', [ $this, 'register_with_hub' ] );

// Don't use:
// add_action( 'init', ... )  // ❌ Too late
// add_action( 'admin_menu', ... )  // ❌ Conflicts with hub
```

> **Note**: In v1.1.0+, Silver Assist appears as a top-level menu item, not under Settings.

### Problem 2: Blank page when clicking "Configure"

**Symptoms:**
- Dashboard works but settings page is blank

**Solution:**
```php
// Verify your callback is valid
$hub->register_plugin(
    'my-plugin',
    'My Plugin',
    [ $this, 'render_settings' ],  // ✅ Method must exist
);

// Verify the method exists
public function render_settings(): void {
    // Your code here
}
```

### Problem 3: Settings are not saved

**Symptoms:**
- Form submits but values are not saved

**Solution:**
```php
// Make sure to use settings_fields with the SAME slug
public function register_settings(): void {
    register_setting( 'my-plugin', 'my_option' );  // Slug: 'my-plugin'
}

public function render_settings(): void {
    ?>
    <form method="post" action="options.php">
        <?php settings_fields( 'my-plugin' ); ?>  <!-- ✅ Same slug -->
        <?php do_settings_sections( 'my-plugin' ); ?>
        <?php submit_button(); ?>
    </form>
    <?php
}
```

### Problem 4: Error "Class SettingsHub not found"

**Symptoms:**
- Fatal error when activating plugin

**Solution:**
```php
// 1. Verify Composer is installed
composer install

// 2. Verify autoloader is loaded
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// 3. Use correct namespace
use SilverAssist\SettingsHub\SettingsHub;

// 4. Always check availability
if ( ! class_exists( SettingsHub::class ) ) {
    // Fallback
}
```

### Problem 5: Hub tabs don't appear

**Symptoms:**
- Navigation tabs between plugins are not shown

**Solution:**
```php
// Tabs are enabled by default
// If you want to disable them:
$hub = SettingsHub::get_instance();
$hub->enable_tabs( false );  // Disable
$hub->enable_tabs( true );   // Enable (default)
```

### Problem 6: Conflict with existing standalone page

**Symptoms:**
- Two menu entries appear for your plugin

**Solution:**
```php
public function __construct() {
    // ❌ INCORRECT - Both registrations are active
    add_action( 'admin_menu', [ $this, 'register_standalone' ] );
    add_action( 'plugins_loaded', [ $this, 'register_with_hub' ] );
}

// ✅ CORRECT - Only one is activated
public function __construct() {
    add_action( 'plugins_loaded', [ $this, 'register_with_hub' ] );
}

public function register_with_hub(): void {
    if ( ! class_exists( SettingsHub::class ) ) {
        // Only if hub is NOT available
        add_action( 'admin_menu', [ $this, 'register_standalone' ] );
        return;
    }
    // Register with hub
}
```

---

## 📝 Implementation Checklist

Use this checklist to ensure the implementation is complete:

- [ ] Installed `silverassist/wp-settings-hub` via Composer
- [ ] Added `use SilverAssist\SettingsHub\SettingsHub;` at the beginning of the file
- [ ] Loaded Composer autoloader in the plugin's main file
- [ ] Created `register_with_hub()` method with class verification
- [ ] Added fallback to standalone page if hub is not available
- [ ] Registered plugin with unique slug, name, and callback
- [ ] Included metadata: description, version, icon_url (optional)
- [ ] Maintained settings registration with WordPress Settings API
- [ ] `render_settings()` callback works correctly
- [ ] Verified settings are saved correctly
- [ ] Tested in WordPress with hub installed
- [ ] Tested in WordPress WITHOUT hub installed (fallback)
- [ ] Updated plugin README with hub information
- [ ] Updated plugin CHANGELOG

---

## 🆘 Support

If you encounter problems during implementation:

1. **Check PHP logs**: `wp-content/debug.log`
2. **Enable WP_DEBUG**: In `wp-config.php`
   ```php
   define( 'WP_DEBUG', true );
   define( 'WP_DEBUG_LOG', true );
   ```
3. **Check browser console**: There may be JavaScript errors
4. **Contact the team**: support@silverassist.com

---

## 📚 Additional Resources

- **Repository**: https://github.com/SilverAssist/wp-settings-hub
- **API Documentation**: See package `README.md`
- **Complete example**: See package `integration-guide.php`
- **WordPress Settings API**: https://developer.wordpress.org/plugins/settings/

---

**Last updated**: October 8, 2025  
**Hub Version**: 1.1.0

> **Breaking Changes in v1.1.0**: The hub now creates a top-level menu instead of appearing under Settings. See [MIGRATION-v1.1.md](MIGRATION-v1.1.md) for upgrade details.

