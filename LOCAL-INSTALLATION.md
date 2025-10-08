# Local Installation Guide - Settings Hub Package

Since the package is not yet published on Packagist, you can install it locally in your WordPress plugins for testing.

> **Note**: Version 1.1.0+ uses a **top-level menu** structure. See [MIGRATION-v1.1.md](MIGRATION-v1.1.md) for details about the changes from v1.0.0.

## 📦 Installation Methods

### Method 1: Using Composer Path Repository (Recommended)

This method allows Composer to manage the package as if it were from Packagist.

#### Step 1: Extract the Package

Extract `silverassist-wp-settings-hub-1.1.0-dev-package.zip` (or latest version) to a location accessible by your plugin:

```bash
# Option A: Inside your plugin (recommended for testing)
cd /path/to/your-plugin
mkdir -p packages
unzip silverassist-wp-settings-hub-1.1.0-dev-package.zip -d packages/wp-settings-hub

# Option B: In a shared location (for multiple plugins)
mkdir -p ~/composer-packages
unzip silverassist-wp-settings-hub-1.1.0-dev-package.zip -d ~/composer-packages/wp-settings-hub
```

#### Step 2: Update Your Plugin's composer.json

Add the local repository to your plugin's `composer.json`:

```json
{
    "name": "silverassist/my-plugin",
    "description": "My WordPress Plugin",
    "type": "wordpress-plugin",
    "require": {
        "php": "^8.3",
        "silverassist/wp-settings-hub": "^1.1"
    },
    "repositories": [
        {
            "type": "path",
            "url": "./packages/wp-settings-hub",
            "options": {
                "symlink": true
            }
        }
    ],
    "autoload": {
        "psr-4": {
            "SilverAssist\\MyPlugin\\": "src/"
        }
    }
}
```

**Note**: If you extracted to `~/composer-packages`, use the full path:
```json
"url": "/Users/YOUR_USERNAME/composer-packages/wp-settings-hub"
```

#### Step 3: Install Dependencies

```bash
cd /path/to/your-plugin
composer install
```

Composer will create a symlink from `vendor/silverassist/wp-settings-hub` to your local package.

#### Step 4: Use in Your Plugin

In your plugin's main file:

```php
<?php
/**
 * Plugin Name: My Plugin
 * Requires PHP: 8.3
 */

declare(strict_types=1);

// Load Composer autoloader
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Now you can use the Settings Hub
use SilverAssist\SettingsHub\SettingsHub;

add_action( 'plugins_loaded', function() {
    if ( ! class_exists( SettingsHub::class ) ) {
        return;
    }

    $hub = SettingsHub::get_instance();
    $hub->register_plugin(
        'my-plugin',
        'My Plugin',
        'my_plugin_render_settings',
        [
            'description' => 'My plugin settings',
            'version'     => '1.0.0',
        ]
    );
});
```

---

### Method 2: Direct Copy (Alternative)

If you don't want to use Composer for the local package:

#### Step 1: Copy Files to Your Plugin

```bash
cd /path/to/your-plugin
mkdir -p vendor/silverassist/wp-settings-hub
unzip silverassist-wp-settings-hub-1.1.0-dev-package.zip -d vendor/silverassist/wp-settings-hub
```

#### Step 2: Manual Autoload Setup

In your plugin's main file:

```php
<?php
/**
 * Plugin Name: My Plugin
 */

declare(strict_types=1);

// Manual autoload for Settings Hub
spl_autoload_register( function ( $class ) {
    $prefix = 'SilverAssist\\SettingsHub\\';
    $base_dir = __DIR__ . '/vendor/silverassist/wp-settings-hub/src/';

    $len = strlen( $prefix );
    if ( strncmp( $prefix, $class, $len ) !== 0 ) {
        return;
    }

    $relative_class = substr( $class, $len );
    $file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

    if ( file_exists( $file ) ) {
        require $file;
    }
});

// Now you can use the Settings Hub
use SilverAssist\SettingsHub\SettingsHub;
```

---

## 🔄 Updating the Package

When you make changes to the Settings Hub:

### Method 1 (Path Repository):

If using symlink (default), changes are automatically reflected. Otherwise:

```bash
cd /path/to/your-plugin
composer update silverassist/wp-settings-hub
```

### Method 2 (Direct Copy):

Delete and re-extract the updated ZIP:

```bash
cd /path/to/your-plugin
rm -rf vendor/silverassist/wp-settings-hub
unzip silverassist-wp-settings-hub-1.1.0-dev-package.zip -d vendor/silverassist/wp-settings-hub
```

---

## ✅ Verify Installation

Create a simple test to verify the package is loaded correctly:

```php
add_action( 'admin_init', function() {
    if ( class_exists( 'SilverAssist\SettingsHub\SettingsHub' ) ) {
        error_log( '✅ Settings Hub is loaded correctly' );
    } else {
        error_log( '❌ Settings Hub is NOT loaded' );
    }
});
```

Check your WordPress debug log at `wp-content/debug.log`.

---

## 🚀 Testing Multiple Plugins

To test the hub with multiple plugins:

1. **Extract once** to a shared location:
   ```bash
   mkdir -p ~/wp-packages
   unzip silverassist-wp-settings-hub-1.1.0-dev-package.zip -d ~/wp-packages/wp-settings-hub
   ```

2. **Reference in each plugin's composer.json**:
   ```json
   {
       "repositories": [
           {
               "type": "path",
               "url": "/Users/YOUR_USERNAME/wp-packages/wp-settings-hub",
               "options": {
                   "symlink": true
               }
           }
       ],
       "require": {
           "silverassist/wp-settings-hub": "^1.1"
       }
   }
   ```

3. **Run `composer install`** in each plugin

All plugins will use the same Settings Hub instance, just like in production!

---

## 📝 Notes

- **Symlink Option**: The `"symlink": true` option creates a symlink instead of copying files, so changes to the hub are immediately reflected in all plugins.
- **Git Ignore**: Add to your plugin's `.gitignore`:
  ```
  /vendor/
  /packages/
  composer.lock
  ```
- **Production**: When ready for production, switch to Packagist by removing the `repositories` section from `composer.json`.

---

## 🆘 Troubleshooting

### "Class SettingsHub not found"

1. Verify the package is in `vendor/silverassist/wp-settings-hub/`
2. Check that `vendor/autoload.php` is being loaded
3. Verify the `src/SettingsHub.php` file exists

### "Composer could not find package"

1. Check the `url` path in repositories is correct and absolute
2. Verify `composer.json` exists in the package directory
3. Try running `composer clear-cache`

### Changes Not Reflected

1. If using symlink, changes should be immediate
2. If not using symlink, run `composer update silverassist/wp-settings-hub`
3. Clear WordPress object cache if using persistent caching

---

**Package Version**: 1.1.0 (Development)  
**Created**: October 7, 2025  
**Updated**: October 8, 2025

> **Important**: Version 1.1.0 introduces a **top-level menu** structure. The hub now appears as a main menu item instead of under Settings. See [MIGRATION-v1.1.md](MIGRATION-v1.1.md) for migration details.
