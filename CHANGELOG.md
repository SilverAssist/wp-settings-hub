# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Initial release of Settings Hub package

## [1.0.0] - 2024-01-XX

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
