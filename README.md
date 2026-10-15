# Silver Assist Settings Hub

[![Software License](https://img.shields.io/badge/license-PolyForm--Noncommercial--1.0.0-blue.svg?style=flat-square)](LICENSE.md)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.2-8892BF.svg?style=flat-square)](https://php.net/)
[![WordPress](https://img.shields.io/badge/wordpress-%3E%3D6.5-21759B.svg?style=flat-square)](https://wordpress.org/)

Centralized settings hub for Silver Assist WordPress plugins. Provides a unified **top-level "Silver Assist"** menu with auto-registration, dynamic dashboard, and optional cross-plugin navigation tabs.

## Features

- **🎯 Auto-Registration**: Plugins register themselves without central configuration
- **📊 Dynamic Dashboard**: Automatically generates overview of all installed Silver Assist plugins
- **🔗 Cross-Plugin Navigation**: Optional tabs for seamless navigation between plugin settings
- **🎨 Beautiful UI**: WordPress-native design with cards and tabs
- **🏢 Top-Level Menu**: Professional menu structure with custom icon and submenus
- **🔒 Type-Safe**: Full PHP 8.2+ type hints and PHPDocs
- **✅ Tested**: PHPUnit 9.6 tests that run against the WordPress Test Suite
- **📦 Composer-Ready**: Installed through Composer (see [Installation](#installation))

## Requirements

- **PHP**: 8.2 or higher
- **WordPress**: 6.5 or higher
- **Composer**: For package management

The hub registers its menu on `admin_menu` only. It supports single-site installs and the per-site admin of a multisite network, and registers nothing in the network admin or user admin.

## Installation

Silver Assist packages are installed from their GitHub repositories with a Composer
`vcs` repository. Those repositories can require authentication, so always configure
a token.

1. **Declare the repository** in your project's root `composer.json`. Composer only
   reads `repositories` from the root package, so every Silver Assist package your
   project needs must be listed there, including transitive ones:

   ```json
   {
     "repositories": [
       { "type": "vcs", "url": "https://github.com/SilverAssist/wp-settings-hub", "no-api": true }
     ],
     "require": {
       "silverassist/wp-settings-hub": "^1.2"
     }
   }
   ```

   With the repository declared, `composer require silverassist/wp-settings-hub:^1.2`
   adds the same constraint from the command line.

2. **Authenticate** with a GitHub token that can read the repository:
   - Locally: `composer config --global github-oauth.github.com <token>`
   - CI: set `COMPOSER_AUTH='{"github-oauth":{"github.com":"<token>"}}'` from a secret
     (a GitHub Actions secret or a Bitbucket variable).

   Never commit a token or an `auth.json`. Without a token, Composer hits GitHub's
   anonymous API limit (60 requests per hour per IP) and falls back to an SSH clone.

   Keep `"no-api": true` on every `vcs` entry: it makes Composer read tags with git instead of
   the GitHub API. Without it, one install without a lock file costs about 100 API requests of
   the token's hourly quota (5,000, shared with the token owner's other API usage), which a busy
   CI can exhaust.

3. **Refresh the lock file** if your project commits `composer.lock`: run
   `composer update --lock` after adding `repositories`, so the lock file's
   `content-hash` matches `composer.json`.

This repository's own `composer.json` declares `vcs` repositories for its Silver Assist development dependencies (`coding-standards`, `wp-coding-standards`), so contributors and CI need the same token. See [CONTRIBUTING.md](https://github.com/SilverAssist/wp-settings-hub/blob/main/CONTRIBUTING.md).

### Troubleshooting

| Symptom | Cause |
|---------|-------|
| `Failed to clone the git@github.com:SilverAssist/wp-settings-hub.git repository, try running in interactive mode...` followed by `Permission denied (publickey)` | The token is missing or has no access to the repository. |
| `remote: Invalid username or token` | The token is invalid or expired. |
| `it could not be found in any version` | The `vcs` entry is missing from the root `composer.json`. |

## Menu Structure

The hub creates a **top-level menu** in the WordPress admin:

```text
WordPress Admin
├── Dashboard
├── Posts
├── Media
├── ...
├── Silver Assist              ← Top-level menu (inline SVG icon)
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
        'tab_title'   => 'My Plugin',  // Optional: label for the sidebar item and the tab
        'plugin_file' => __FILE__,     // Optional but recommended: path to main plugin file
    ]
);
```

**Note:** The `plugin_file` parameter is optional but recommended. It lets the hub find its stylesheet under your plugin's `vendor/` directory. See [Asset URL resolution](#asset-url-resolution) for the exact rules.

### 2. Render Your Settings Page

Your callback function receives no parameters and should render the settings content:

```php
public function render_settings(): void {
    ?>
    <p>Your plugin settings go here.</p>

    <form method="post" action="options.php">
        <?php
        settings_fields( 'my_plugin_settings' );
        do_settings_sections( 'my_plugin_settings' );
        submit_button();
        ?>
    </form>
    <?php
}
```

### 3. That's It!

Your plugin will now appear:

- A card on the dashboard showing the name, description, and version
- A submenu item under "Silver Assist", labelled with `tab_title` (or the name when `tab_title` is not set)
- A tab in the tabs navigation. Tabs are enabled by default, see [Disable Tabs Navigation](#disable-tabs-navigation)

---

## Complete Example

See [`integration-guide.php`](https://github.com/SilverAssist/wp-settings-hub/blob/main/integration-guide.php) for a complete working example.

## Advanced Usage

### Disable Tabs Navigation

Tabs are enabled by default. If you prefer not to show tabs for cross-plugin navigation:

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
    // 'version' is optional, so it may not be set.
    echo $plugin['name'] . ' - ' . ( $plugin['version'] ?? '' );
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

> ⚠️ **Important**: When implementing update checks, you must properly synchronize both WordPress caches (plugin-specific and system-wide) to ensure the Updates page displays current information. See [IMPLEMENTATION.md](https://github.com/SilverAssist/wp-settings-hub/blob/main/IMPLEMENTATION.md#example-2-plugin-with-update-button) for the complete implementation with cache synchronization.

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
    
    // IMPORTANT: This is a simplified example. The complete implementation
    // requires proper cache clearing with delete_site_transient() and wp_update_plugins().
    // See https://github.com/SilverAssist/wp-settings-hub/blob/main/IMPLEMENTATION.md for the full code.
    public function render_update_check( string $slug ): void {
        ?>
        jQuery.post(ajaxurl, {
            action: 'my_plugin_check_updates',
            nonce: '<?php echo esc_js( wp_create_nonce( 'my_plugin_updates_nonce' ) ); ?>'
        }).done(function(response) {
            if (response.success && response.data.update_available) {
                alert('<?php esc_html_e( 'Update available!', 'my-plugin' ); ?>');
                window.location.href = '<?php echo esc_js( admin_url( 'update-core.php' ) ); ?>';
            } else {
                alert('<?php esc_html_e( 'Already up to date', 'my-plugin' ); ?>');
            }
        });
        <?php
    }
}
```

### Restrict a Plugin to a Capability

Every hub page requires `manage_options` by default. A plugin can ask for a different capability with the `capability` argument (since 1.3.0):

```php
$hub->register_plugin(
    'my-plugin',
    'My Plugin',
    array( $this, 'render_settings' ),
    array(
        'capability' => 'edit_posts',
    )
);
```

The capability decides who gets that plugin's submenu item, dashboard card, tab and page. Users who do not hold it see none of them, and a direct request for the page is answered with a 403.

What stays on `manage_options`:

- The "Silver Assist" menu and its Dashboard. The Dashboard tab is hidden for users who cannot open it, so nobody sees a link that ends in a 403. WordPress links the top-level menu item to the first page the user may open, so an editor who only holds one plugin's capability lands on that plugin's page.
- Plugins that do not pass `capability`.

Rules:

- The value is a capability name made of letters, digits, underscores and hyphens. Anything else (empty, not a string, containing spaces) is reported with `_doing_it_wrong()` and the plugin falls back to `manage_options`, so a typo never opens a page wider than the default.
- Capabilities are not ordered. A user who holds a custom capability but not `manage_options` sees only the plugins that ask for it, and an administrator who lacks a custom capability does not see that plugin.
- Meta capabilities that need an object ID, such as `edit_post`, are not supported.
- The hub's check and the check inside your settings callback are separate. If you lower the capability here, make the callback check the same one (the examples in this repository check `manage_options`), or the page opens and then refuses the user.
- If the plugin saves its settings through the Settings API, WordPress checks `manage_options` again on `options.php`. Lower it for your option group with the `option_page_capability_{$option_group}` filter, otherwise the page loads but saving ends in a 403.

Two filters change the capabilities. They run when the hub builds the menu on `admin_menu` and again when it renders, so add them before `admin_menu`:

| Filter | Arguments | Use |
| --- | --- | --- |
| `silverassist_settings_hub_plugin_capability` | `$capability`, `$slug`, `$plugin` | Change the capability of one plugin |
| `silverassist_settings_hub_dashboard_capability` | `$capability` | Change the capability of the menu and the Dashboard (`manage_options` by default) |

```php
// Let editors open the Dashboard as well.
add_filter(
    'silverassist_settings_hub_dashboard_capability',
    static fn(): string => 'edit_posts'
);
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

See [`integration-guide.php`](https://github.com/SilverAssist/wp-settings-hub/blob/main/integration-guide.php) for complete working examples including:

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
- `$name` (string): Display name for the plugin. It is the page title, the heading (`<h1>`) of the plugin's settings page and the heading of its dashboard card
- `$callback` (callable): Function to render the plugin's settings page
- `$args` (array, optional): Additional arguments:
  - `description` (string): Short description shown on dashboard card
  - `version` (string): Plugin version number, shown on the dashboard card and in a badge next to the heading of the plugin's settings page
  - `tab_title` (string): Label for both the sidebar submenu item and the tab (defaults to `$name`)
  - `plugin_file` (string): Absolute path to the plugin's main file, used to find the hub stylesheet (see [Asset URL resolution](#asset-url-resolution))
  - `capability` (string): Capability a user needs to see and open the plugin's page, `manage_options` by default (see [Restrict a Plugin to a Capability](#restrict-a-plugin-to-a-capability))
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

**Behaviour notes**:

- Registering a slug that is already registered replaces its data silently. Nothing is merged with the earlier registration and no notice is raised.
- `plugin_file` is not stored with the plugin data, so it does not appear in `get_plugins()`.

#### Asset URL resolution

The hub loads its stylesheet (`assets/css/settings-hub.css`) from the package directory. It finds the URL in this order:

1. **From `plugin_file`.** Only the first non-empty `plugin_file` passed to `register_plugin()` is kept, and later ones are ignored. If `vendor/silverassist/wp-settings-hub/assets/css/settings-hub.css` exists inside the directory of that file, the URL is built from the plugin's URL (`plugin_dir_url()`).
2. **From the package's own location.** Otherwise the hub looks at the directory it was loaded from. That path becomes a URL only when the package lives under `ABSPATH`, because the hub swaps `ABSPATH` for the site URL.
3. **No stylesheet.** If neither works (for example a package installed outside `ABSPATH`), no stylesheet is loaded and nothing is reported, so the pages render without the hub styles.

Pass `plugin_file => __FILE__` from your main plugin file, and keep the package in your plugin's `vendor/` directory, to avoid step 3.

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

Setup, running the tests, the quality checks, the commit convention and the release checklist are documented in [CONTRIBUTING.md](https://github.com/SilverAssist/wp-settings-hub/blob/main/CONTRIBUTING.md).

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

See [CONTRIBUTING.md](https://github.com/SilverAssist/wp-settings-hub/blob/main/CONTRIBUTING.md) for detailed contribution guidelines.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history and changes.

---

**Made with ❤️ by [Silver Assist](https://silverassist.com)**
