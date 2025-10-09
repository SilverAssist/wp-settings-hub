# Silver Assist Settings Hub

[![Latest Version on Packagist](https://img.shields.io/packagist/v/silverassist/wp-settings-hub.svg?style=flat-square)](https://packagist.org/packages/silverassist/wp-settings-hub)
[![Software License](https://img.shields.io/badge/license-PolyForm--Noncommercial--1.0.0-blue.svg?style=flat-square)](LICENSE.md)
[![Total Downloads](https://img.shields.io/packagist/dt/silverassist/wp-settings-hub.svg?style=flat-square)](https://packagist.org/packages/silverassist/wp-settings-hub)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.3-8892BF.svg?style=flat-square)](https://php.net/)
[![WordPress](https://img.shields.io/badge/wordpress-%3E%3D6.5-21759B.svg?style=flat-square)](https://wordpress.org/)

Centralized settings hub for Silver Assist WordPress plugins. Provides a unified **top-level "Silver Assist"** menu with auto-registration, dynamic dashboard, and optional cross-plugin navigation tabs.

## Features

- **🎯 Auto-Registration**: Plugins register themselves without central configuration
- **📊 Dynamic Dashboard**: Automatically generates overview of all installed Silver Assist plugins
- **🔗 Cross-Plugin Navigation**: Optional tabs for seamless navigation between plugin settings
- **🎨 Beautiful UI**: WordPress-native design with cards and tabs
- **🏢 Top-Level Menu**: Professional menu structure with custom icon and submenus
- **🔒 Type-Safe**: Full PHP 8.3+ type hints and PHPDocs
- **✅ Well-Tested**: Comprehensive test suite with PHPUnit 10
- **📦 Composer-Ready**: Easy integration via `composer require`

## Requirements

- **PHP**: 8.3 or higher
- **WordPress**: 6.5 or higher
- **Composer**: For package management

## Installation

Install via Composer:

```bash
composer require silverassist/wp-settings-hub
```

## Menu Structure

The hub creates a **top-level menu** in the WordPress admin:

```
WordPress Admin
├── Dashboard
├── Posts
├── Media
├── ...
├── Silver Assist 🛡️           ← Top-level menu
│   ├── Dashboard              ← Hub overview
│   ├── Post Revalidate        ← Your plugin
│   └── [More plugins...]      ← More plugins
└── ...
```

**Dashboard URL**: `admin.php?page=silver-assist`  
**Plugin URLs**: `admin.php?page={your-plugin-slug}`

## Quick Start

### 1. Basic Integration

Add this to your plugin's main file or initialization class:

```php
<?php
use SilverAssist\SettingsHub\SettingsHub;

// Register your plugin with the hub
$hub = SettingsHub::get_instance();
$hub->register_plugin(
    'my-plugin',                    // Unique slug
    'My Plugin',                    // Display name
    [ $this, 'render_settings' ],   // Callback to render settings
    [
        'description' => 'Description of my plugin',
        'version'     => '1.0.0',
        'tab_title'   => 'My Plugin', // Optional: custom tab title
    ]
);
```

### 2. Render Your Settings Page

Your callback function receives no parameters and should render the settings content:

```php
public function render_settings(): void {
    ?>
    <div class="silverassist-plugin-settings">
        <p>Your plugin settings go here.</p>
        
        <form method="post" action="options.php">
            <?php
            settings_fields( 'my_plugin_settings' );
            do_settings_sections( 'my_plugin_settings' );
            submit_button();
            ?>
        </form>
    </div>
    <?php
}
```

### 3. That's It!

Your plugin will now appear:

- A card on the dashboard showing name, description, and version
- A submenu item under "Silver Assist"
- Optional: in the tabs navigation if tabs are enabled

---

## Complete Example

See [`integration-guide.php`](integration-guide.php) for a complete working example.

## Advanced Usage

### Disable Tabs Navigation

If you prefer not to show tabs for cross-plugin navigation:

```php
$hub = SettingsHub::get_instance();
$hub->enable_tabs( false );
```

### Check Plugin Registration

```php
$hub = SettingsHub::get_instance();

if ( $hub->is_plugin_registered( 'my-plugin' ) ) {
    // Plugin is registered
}
```

### Get All Registered Plugins

```php
$hub = SettingsHub::get_instance();
$plugins = $hub->get_plugins();

foreach ( $plugins as $slug => $plugin ) {
    echo $plugin['name'] . ' - ' . $plugin['version'];
}
```

### Get Parent Menu Slug

```php
$hub = SettingsHub::get_instance();
$parent_slug = $hub->get_parent_slug(); // Returns 'silver-assist'
```

### Add Custom Dashboard Actions

Add action buttons to your plugin's dashboard card:

```php
$hub->register_plugin(
    'my-plugin',
    'My Plugin',
    [ $this, 'render_settings' ],
    [
        'description' => 'Plugin description',
        'version'     => '1.0.0',
        'actions'     => [
            // URL-based action (direct link)
            [
                'label' => 'Documentation',
                'url'   => 'https://docs.example.com',
                'class' => 'button',
            ],
            // Callback-based action (JavaScript)
            [
                'label'    => 'Check Updates',
                'callback' => function() {
                    ?>
                    alert('Checking for updates...');
                    <?php
                },
                'class' => 'button button-primary',
            ],
        ],
    ]
);
```

**Integration with wp-github-updater**:

If you're using the `silverassist/wp-github-updater` package, you can add a "Check Updates" button:

```php
use SilverAssist\WpGithubUpdater\Updater;
use SilverAssist\WpGithubUpdater\UpdaterConfig;

class My_Plugin {
    private ?Updater $updater = null;

    public function init_updater(): void {
        if ( ! class_exists( UpdaterConfig::class ) ) {
            return;
        }

        $config = new UpdaterConfig(
            __FILE__,
            'SilverAssist/my-plugin',
            array(
                'text_domain' => 'my-plugin',
                'ajax_action' => 'my_plugin_check_updates',
                'ajax_nonce'  => 'my_plugin_updates_nonce',
            )
        );

        $this->updater = new Updater( $config );
    }

    public function register_with_hub(): void {
        $hub = SettingsHub::get_instance();
        
        $actions = array();
        
        // Add update checker if available
        if ( null !== $this->updater ) {
            $actions[] = array(
                'label'    => __( 'Check Updates', 'my-plugin' ),
                'callback' => array( $this, 'render_update_check' ),
                'class'    => 'button',
            );
        }
        
        $hub->register_plugin(
            'my-plugin',
            'My Plugin',
            array( $this, 'render_settings' ),
            array(
                'description' => 'Plugin with GitHub updates',
                'version'     => '1.0.0',
                'actions'     => $actions,
            )
        );
    }
    
    public function render_update_check( string $slug ): void {
        // The updater provides manualVersionCheck() AJAX endpoint
        ?>
        jQuery.post(ajaxurl, {
            action: 'my_plugin_check_updates',
            nonce: '<?php echo esc_js( wp_create_nonce( 'my_plugin_updates_nonce' ) ); ?>'
        }).done(function(response) {
            if (response.success && response.data.update_available) {
                alert('<?php esc_html_e( 'Update available!', 'my-plugin' ); ?>');
                window.location.href = '<?php echo esc_js( admin_url( 'plugins.php?plugin_status=upgrade' ) ); ?>';
            } else {
                alert('<?php esc_html_e( 'Already up to date', 'my-plugin' ); ?>');
            }
        });
        <?php
    }
}
```

### Using WordPress Action Hooks

You can also add custom actions using the `silverassist_settings_hub_plugin_actions` hook:

```php
add_action( 'silverassist_settings_hub_plugin_actions', function( $slug, $plugin ) {
    if ( $slug !== 'my-plugin' ) {
        return;
    }
    
    ?>
    <a href="<?php echo esc_url( admin_url( 'tools.php?page=diagnostics' ) ); ?>" class="button">
        <?php esc_html_e( 'Run Diagnostics', 'my-plugin' ); ?>
    </a>
    <?php
}, 10, 2 );
```

**Hook Parameters**:

- `$slug` (string): The plugin slug
- `$plugin` (array): The plugin data including name, callback, description, version, etc.

---

## Integration Examples

See [`integration-guide.php`](integration-guide.php) for complete working examples including:

- Basic plugin integration
- wp-github-updater integration with "Check Updates" button
- Custom action buttons
- WordPress action hook usage

---

## API Reference

### `SettingsHub::get_instance()`

Get singleton instance of the hub.

**Returns**: `SettingsHub` - Singleton instance

### `register_plugin( string $slug, string $name, callable $callback, array $args = [] )`

Register a plugin with the settings hub.

**Parameters**:

- `$slug` (string): Unique plugin slug (e.g., `'post-revalidate'`)
- `$name` (string): Display name for the plugin
- `$callback` (callable): Function to render the plugin's settings page
- `$args` (array, optional): Additional arguments:
  - `description` (string): Short description shown on dashboard card
  - `version` (string): Plugin version number
  - `tab_title` (string): Custom title for tab (defaults to `$name`)
  - `actions` (array): Custom action buttons for the dashboard card

**Action Button Structure**:

Each action in the `actions` array should be an associative array with:
- `label` (string, required): Button text
- `url` (string, optional): Direct link URL (for navigation)
- `callback` (callable, optional): JavaScript code to execute (for interactive actions)
- `class` (string, optional): CSS classes for the button (default: `'button'`)

**Example with Actions**:

```php
$hub->register_plugin(
    'my-plugin',
    'My Plugin',
    [ $this, 'render_settings' ],
    [
        'description' => 'Plugin description',
        'version'     => '1.0.0',
        'actions'     => [
            [
                'label' => 'Check Updates',
                'callback' => [ $this, 'render_update_check_script' ],
                'class' => 'button',
            ],
            [
                'label' => 'Documentation',
                'url'   => 'https://docs.example.com',
                'class' => 'button',
            ],
        ],
    ]
);
```

**Returns**: `void`

### `enable_tabs( bool $enable )`

Enable or disable tabs navigation.

**Parameters**:
- `$enable` (bool): `true` to enable tabs, `false` to disable

**Returns**: `void`

### `is_tabs_enabled()`

Check if tabs are enabled.

**Returns**: `bool` - `true` if enabled, `false` otherwise

### `get_plugins()`

Get all registered plugins.

**Returns**: `array` - Associative array of registered plugins keyed by slug

### `is_plugin_registered( string $slug )`

Check if a plugin is registered.

**Parameters**:
- `$slug` (string): Plugin slug to check

**Returns**: `bool` - `true` if registered, `false` otherwise

### `get_parent_slug()`

Get the parent menu slug.

**Returns**: `string` - Parent menu slug (`'silver-assist'`)

## Development

### Install Dependencies

```bash
composer install
```

### Run Tests

```bash
composer test
```

### Check Code Standards

```bash
composer phpcs
```

### Run Static Analysis

```bash
composer phpstan
```

### Run All Quality Checks

```bash
composer qa
```

## Architecture

### Singleton Pattern

The `SettingsHub` class uses the singleton pattern to ensure only one instance exists. This prevents duplicate menu creation and maintains a single source of truth for registered plugins.

### Auto-Discovery

Plugins register themselves by calling `register_plugin()`. The hub automatically:
1. Creates the parent "Silver Assist" menu (once)
2. Adds the plugin as a submenu item
3. Generates a dashboard card for the plugin
4. Updates the tabs navigation (if enabled)

### Hooks

The hub uses WordPress's `admin_menu` hook with priority 5 to register menus early. This ensures the parent menu exists before any submenus are registered.

## License

This package is licensed under the [PolyForm Noncommercial License 1.0.0](LICENSE.md).

**TL;DR**: Free for personal and noncommercial use. Commercial use requires a separate license.

## Support

For issues, feature requests, or questions:
- **Email**: support@silverassist.com

## Contributing

This is a proprietary package for Silver Assist plugins. Contributions are limited to Silver Assist team members.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history and changes.

---

**Made with ❤️ by [Silver Assist](https://silverassist.com)**
