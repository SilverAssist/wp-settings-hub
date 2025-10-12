# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- Lowered PHP requirement from 8.3 to 8.2 for broader compatibility
- Updated all documentation to reflect PHP 8.2+ requirement
- GitHub Actions now tests on PHP 8.2, 8.3, and 8.4

## [1.1.2] - 2025-10-10

### Documentation

- **MAJOR UPDATE**: Complete rewrite of IMPLEMENTATION.md based on real-world implementation patterns
- Added comprehensive "Common Integration Errors" section with actual solutions from production plugins
- Added detailed explanation of all 4 critical integration errors and their fixes
- Added complete working examples tested in production environments
- Added "Update Button Implementation" section with production-ready JavaScript
- Added "Asset Loading Configuration" with CSS best practices and Grunt setup
- Added "Security & Permissions" section with complete validation patterns
- Added comprehensive "Testing Checklist" for pre-deployment validation
- Added "Best Practices" section with 10 critical requirements
- All examples now use PHP 8.3+ strict types and WordPress Coding Standards
- Documentation now reflects Settings Hub v1.1.1+ requirements (capability parameter)

### Context

- New guide combines theoretical documentation with practical, tested implementation patterns
- Based on actual integration in silver-assist-post-revalidate plugin
- Addresses real errors encountered during development
- Provides complete, copy-paste ready code examples
- Includes performance optimization and security hardening patterns

## [1.1.1] - 2025-10-09

### Fixed

- **CRITICAL**: Updated all wp-github-updater integration examples with proper WordPress cache synchronization
- Examples now include `delete_site_transient('update_plugins')` to clear WordPress core cache
- Examples now include `wp_update_plugins()` to force immediate update check
- Fixed redirect URL from `plugins.php?plugin_status=upgrade` to `update-core.php` for better UX
- Added comprehensive AJAX handler implementation showing complete cache clearing flow
- Added "Common Mistakes to Avoid" section explaining the two-tier cache system
- Added "Testing Your Implementation" section with step-by-step validation

### Documentation

- Updated `integration-guide.php` with complete cache synchronization pattern
- Updated `IMPLEMENTATION.md` with detailed explanation of WordPress two-tier cache system
- Updated `README.md` with warning note about cache synchronization requirements
- Added inline comments explaining each step of the cache clearing process
- Clarified that WordPress uses both plugin-specific cache AND system-wide cache

### Context

- Without proper cache synchronization, the "Check Updates" button would trigger successfully but `update-core.php` would show stale data
- Users would see admin notification about updates but couldn't actually install them
- This fix ensures both caches are cleared and WordPress is forced to query GitHub API immediately

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
