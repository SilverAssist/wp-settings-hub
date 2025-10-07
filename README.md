# Silver Assist Settings Hub

[![License](https://img.shields.io/badge/license-PolyForm%20Noncommercial-blue.svg)](LICENSE.md)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.3-8892BF.svg)](https://php.net/)
[![WordPress](https://img.shields.io/badge/wordpress-%3E%3D6.5-21759B.svg)](https://wordpress.org/)

Centralized settings hub for Silver Assist WordPress plugins. Provides a unified **Settings > Silver Assist** menu with auto-registration, dynamic dashboard, and optional cross-plugin navigation tabs.

## Features

- **🎯 Auto-Registration**: Plugins register themselves without central configuration
- **📊 Dynamic Dashboard**: Automatically generates overview of all installed Silver Assist plugins
- **🔗 Cross-Plugin Navigation**: Optional tabs for seamless navigation between plugin settings
- **🎨 Beautiful UI**: WordPress-native design with cards and tabs
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
        'icon_url'    => plugin_dir_url( __FILE__ ) . 'assets/icon.png',
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

Your plugin now appears under **Settings > Silver Assist** with:
- A card on the dashboard showing name, description, version, and icon
- A dedicated settings page with optional tabs navigation
- Automatic cross-plugin navigation (if tabs are enabled)

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

## Migration from Standalone Settings

If your plugin currently has standalone settings under the Settings menu:

### Before (Standalone)

```php
add_action( 'admin_menu', function() {
    add_options_page(
        'My Plugin Settings',
        'My Plugin',
        'manage_options',
        'my-plugin',
        [ $this, 'render_settings' ]
    );
} );
```

### After (With Hub)

```php
use SilverAssist\SettingsHub\SettingsHub;

$hub = SettingsHub::get_instance();
$hub->register_plugin(
    'my-plugin',
    'My Plugin',
    [ $this, 'render_settings' ],
    [
        'description' => 'My plugin description',
        'version'     => '1.0.0',
    ]
);
```

**Note**: Remove your old `add_options_page` call to avoid duplicate menu items.

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
  - `icon_url` (string): URL to plugin icon (48x48px recommended)
  - `description` (string): Short description shown on dashboard card
  - `version` (string): Plugin version number
  - `tab_title` (string): Custom title for tab (defaults to `$name`)

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
- **GitHub Issues**: [silverassist/wp-settings-hub](https://github.com/SilverAssist/wp-settings-hub/issues)
- **Email**: support@silverassist.com

## Contributing

This is a proprietary package for Silver Assist plugins. Contributions are limited to Silver Assist team members.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history and changes.

## Credits

Developed by [Silver Assist](https://silverassist.com) team.
