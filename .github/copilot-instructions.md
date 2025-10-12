# GitHub Copilot Instructions for Silver Assist Settings Hub

## Project Context

This is **silverassist/wp-settings-hub**, a Composer package that provides centralized settings management for Silver Assist WordPress plugins.

### Purpose
- Create a unified "Settings > Silver Assist" menu for all Silver Assist plugins
- Auto-discover and register plugins without central configuration
- Generate a dynamic dashboard showing all installed Silver Assist plugins
- Provide optional tabs for cross-plugin navigation

### Architecture
- **Language**: PHP 8.2+ with strict types
- **Pattern**: Singleton for SettingsHub class
- **WordPress**: 6.5+ (uses Settings API, admin menus, hooks)
- **Testing**: PHPUnit 10 with Brain Monkey for WordPress mocks
- **Standards**: WordPress Coding Standards, Slevomat, PHPStan Level 8
- **License**: PolyForm Noncommercial 1.0.0
- **Namespace**: `SilverAssist\SettingsHub`

## Code Standards

### PHP Requirements
- PHP 8.2+ features required
- Use `declare(strict_types=1);` in all files
- Full type hints for parameters and return types
- Use typed properties
- Follow PSR-4 autoloading

### WordPress Integration
- Use WordPress hooks: `add_action`, `add_options_page`, `add_submenu_page`
- Follow WordPress Settings API patterns
- Escape all output: `esc_html()`, `esc_attr()`, `esc_url()`
- Use translation functions: `__()`, `esc_html_e()`
- Text domain: `silverassist-settings-hub`

### Code Style
- WordPress Coding Standards (WPCS)
- Slevomat Coding Standard
- Tabs for indentation (WordPress convention)
- Yoda conditions for comparisons
- PHPDocs for all classes, methods, properties

### Testing
- PHPUnit 10 test suite in `tests/`
- Brain Monkey for mocking WordPress functions
- Test coverage for all public methods
- Mock WordPress functions in setUp()
- Use descriptive test method names

## File Structure

```
wp-settings-hub/
├── src/
│   └── SettingsHub.php        # Main hub class
├── tests/
│   ├── bootstrap.php          # Test bootstrap with Brain Monkey
│   └── SettingsHubTest.php    # Test suite
├── .github/
│   ├── workflows/
│   │   └── release.yml        # CI/CD for releases
│   └── copilot-instructions.md
├── composer.json              # Package configuration
├── phpunit.xml                # PHPUnit configuration
├── phpcs.xml                  # Code standards config
├── phpstan.neon               # Static analysis config
├── .gitignore
├── LICENSE.md                 # PolyForm Noncommercial
├── README.md                  # Documentation
├── CHANGELOG.md               # Version history
└── integration-guide.php      # Integration examples
```

## Key Classes and Methods

### SettingsHub Class
- **Pattern**: Singleton (`get_instance()`)
- **Purpose**: Centralized registration and menu management
- **Main Methods**:
  - `register_plugin()`: Register a plugin with the hub
  - `enable_tabs()`: Enable/disable tabs navigation
  - `render_dashboard()`: Render main dashboard
  - `get_plugins()`: Get all registered plugins
  - `is_plugin_registered()`: Check if plugin exists

### Plugin Registration
Plugins register with:
```php
$hub = SettingsHub::get_instance();
$hub->register_plugin(
    'plugin-slug',
    'Plugin Name',
    [ $this, 'render_settings' ],
    [
        'description' => '...',
        'version' => '1.0.0',
        'tab_title' => '...',
    ]
);
```

## Development Workflow

### Adding Features
1. Update `src/SettingsHub.php` with new functionality
2. Add corresponding tests in `tests/SettingsHubTest.php`
3. Run quality checks: `composer qa`
4. Update README.md if API changes
5. Update CHANGELOG.md with changes

### Quality Checks
```bash
composer phpcs        # Check code standards
composer phpcs:fix    # Fix auto-fixable issues
composer phpstan      # Run static analysis
composer test         # Run test suite
composer qa           # Run all checks
```

### Making Changes
- All code must pass PHPCS, PHPStan Level 8, and tests
- Use WordPress-native UI components and classes
- Maintain backward compatibility in minor versions
- Follow semantic versioning for releases
- PHP 8.2+ required for type system features

## Common Patterns

### WordPress Function Calls
All WordPress functions are global and should be called without namespace:
```php
add_action( 'admin_menu', [ $this, 'method' ] );
add_options_page( ... );
esc_html_e( 'Text', 'silverassist-settings-hub' );
```

### Type Hints
Use array shapes for complex arrays:
```php
/**
 * @param array{
 *     name: string,
 *     slug: string,
 *     callback: callable,
 *     description?: string,
 *     version?: string
 * } $plugin Plugin data.
 */
```

### Testing WordPress Functions
In tests, mock WordPress functions with Brain Monkey:
```php
Functions\when( 'add_action' )->justReturn( true );
Functions\when( 'esc_html' )->returnArg();
Functions\expect( 'add_options_page' )->once()->with( ... );
```

## Integration Points

### Plugin Integration
Plugins should:
1. Check if `SettingsHub` class exists (optional dependency)
2. Call `register_plugin()` on `init` or `plugins_loaded` hook
3. Provide a fallback standalone settings page if hub not available
4. Use WordPress Settings API for actual settings (hub only handles menus)

### Fallback Pattern
```php
if ( ! class_exists( SettingsHub::class ) ) {
    // Fallback: register standalone settings page
    add_action( 'admin_menu', [ $this, 'register_standalone' ] );
    return;
}

// Hub available: register with hub
$hub = SettingsHub::get_instance();
$hub->register_plugin( ... );
```

## Release Process

1. Update version in CHANGELOG.md
2. Commit changes
3. Create git tag: `git tag -a v1.x.x -m "Release v1.x.x"`
4. Push tag: `git push origin v1.x.x`
5. GitHub Actions automatically:
   - Validates code (PHPCS, PHPStan)
   - Runs tests on PHP 8.2, 8.3 and 8.4
   - Creates release with ZIP archive
   - Extracts changelog for release notes

## Important Notes

- **Singleton**: SettingsHub must be singleton to prevent duplicate menus
- **Hook Priority**: Register menus on `admin_menu` with priority 5
- **Escaping**: Always escape output with WordPress functions
- **Fallback**: Plugins must work without the hub (graceful degradation)
- **License**: PolyForm Noncommercial - free for noncommercial use only
- **WordPress Compatibility**: 6.5+ required for menu APIs
- **PHP Version**: 8.2+ required for type system features

## Related Projects

- **silver-assist-post-revalidate**: First plugin to integrate this hub
- **wp-github-updater**: Used for automatic updates in plugins
- All projects use PolyForm Noncommercial 1.0.0 license
- All projects follow same coding standards

## Text Domain
Use `silverassist-settings-hub` for all translatable strings in the hub code.
Plugins using the hub should use their own text domain for their settings pages.
