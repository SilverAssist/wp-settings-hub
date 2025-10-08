# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed
- Improved menu structure from Settings submenu to top-level menu
- Dashboard URL changed from `options-general.php?page=silver-assist` to `admin.php?page=silver-assist`
- Plugin URLs changed from `options-general.php?page={slug}` to `admin.php?page={slug}`
- "Silver Assist" now appears as a top-level menu item with dashicons-shield icon
- Plugins now appear as proper submenus under "Silver Assist" parent menu

### Added
- Dashboard submenu item under Silver Assist for better UX
- Custom icon (dashicons-shield) for Silver Assist menu
- Menu positioned at priority 80 in the admin menu
- Comprehensive implementation guide (IMPLEMENTATION.md)
- Local installation guide (LOCAL-INSTALLATION.md)

## [1.0.0] - 2025-10-07

### Added
- Core `SettingsHub` class with singleton pattern
- Plugin registration system with `register_plugin()` method
- Auto-generated parent "Silver Assist" menu under Settings
- Dynamic dashboard showing all registered plugins with cards
- Optional cross-plugin tabs navigation
- Support for plugin metadata (name, description, version, icon)
- Comprehensive PHPUnit test suite with WordPress mocks
- Full PHP 8.3+ type hints and strict types
- PSR-4 autoloading support
- WordPress Coding Standards compliance (PHPCS)
- PHPStan Level 8 static analysis
- Complete API documentation
- Integration guide with working examples
- GitHub Actions CI/CD workflow for automated releases
- PolyForm Noncommercial 1.0.0 license

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
