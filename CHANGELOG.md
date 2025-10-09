# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.1.0] - 2025-10-09

### Added

- Custom action buttons support for dashboard plugin cards
- `actions` parameter in `register_plugin()` for adding custom buttons
- Support for URL-based actions (direct links)
- Support for callback-based actions (JavaScript inline execution)
- New `render_action_button()` method for rendering action buttons
- WordPress action hook `silverassist_settings_hub_plugin_actions` for extensibility
- Comprehensive documentation for wp-github-updater integration
- Example implementations showing "Check Updates" button pattern
- Complete integration guide for custom dashboard actions

### Documentation

- Updated `integration-guide.php` with complete wp-github-updater example using real API
- Added "Custom Dashboard Actions" section in `IMPLEMENTATION.md`
- Updated `README.md` API reference with `actions` parameter documentation
- All examples verified against `silverassist/wp-github-updater` v1.1.4

### Technical

- Uses `UpdaterConfig` for proper updater initialization
- AJAX integration with updater's built-in `manualVersionCheck()` endpoint
- Proper nonce security and error handling in JavaScript
- Redirects to `plugins.php?plugin_status=upgrade` on update available

## [1.0.0] - 2025-10-08

### Added

- Core `SettingsHub` class with singleton pattern
- Plugin registration system with `register_plugin()` method
- Top-level "Silver Assist" menu with dashicons-shield icon
- Dynamic dashboard showing all registered plugins with cards
- Dashboard submenu item under Silver Assist for better UX
- Menu positioned at priority 80 in the admin menu
- Optional cross-plugin tabs navigation
- Support for plugin metadata (name, description, version, tab_title)
- Comprehensive PHPUnit test suite with WordPress mocks (10 tests, 35 assertions)
- Full PHP 8.3+ type hints and strict types
- PSR-4 autoloading support
- WordPress Coding Standards compliance (PHPCS)
- PHPStan Level 8 static analysis
- Complete API documentation in README.md
- Comprehensive implementation guide (IMPLEMENTATION.md)
- integration-guide.php with complete working examples
- GitHub Actions CI/CD workflow for automated releases
- PolyForm Noncommercial 1.0.0 license

### Changed

- Menu structure: top-level menu instead of Settings submenu
- Dashboard URL: `admin.php?page=silver-assist` (not `options-general.php`)
- Plugin URLs: `admin.php?page={slug}` format
- Plugins now appear as proper submenus under "Silver Assist" parent menu

### Documentation

- Comprehensive README.md with API reference
- integration-guide.php with complete working example
- PHPDocs for all public methods
- Usage examples for common scenarios

### Quality

- 100% test coverage for core functionality
- WordPress Coding Standards (WPCS) compliance
- Slevomat Coding Standard rules
- PHPStan Level 8 analysis
- Automated quality checks in CI/CD

[Unreleased]: https://github.com/SilverAssist/wp-settings-hub/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/SilverAssist/wp-settings-hub/releases/tag/v1.0.0
