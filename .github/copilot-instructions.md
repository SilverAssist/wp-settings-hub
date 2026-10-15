# Copilot Instructions for Silver Assist Settings Hub

`silverassist/wp-settings-hub` is a Composer package (one class, `src/SettingsHub.php`) that gives Silver Assist WordPress plugins a shared top-level Silver Assist admin menu. Plugins register themselves with the hub, which renders a dashboard of cards for them and optional navigation tabs between their settings pages.

This file is a pointer. The details live in the docs linked at the end, so do not restate them here.

## Conventions

- PHP 8.2+, `declare(strict_types=1);` in every file, full type hints, typed properties.
- Namespace `SilverAssist\SettingsHub`, PSR-4 from `src/`.
- Tabs for indentation, WordPress coding standards through the shared `SilverAssistWP` ruleset (`phpcs.xml`).
- Prefix rule for globals: functions, hooks and options start with `silverassist_settings_hub`, or live in the `SilverAssist\SettingsHub` namespace.
- PHPStan level 8 on `src` and `tests`.
- Escape all output (`esc_html()`, `esc_attr()`, `esc_url()`) and use Yoda conditions.
- Text domain for strings in the hub code: `silverassist-settings-hub`.
- PHPDoc on every class, method and property.
- Supported versions are PHP 8.2+ and WordPress 6.5+.

## Quality Commands

```bash
composer phpcs      # Check code standards
composer phpcbf     # Auto-fix code standards
composer phpstan    # Static analysis (level 8)
composer test       # Tests without coverage
composer phpunit    # Tests with the coverage reports
composer qa         # phpcs + phpstan + test
```

The tests extend `WP_UnitTestCase`, so they need the WordPress Test Suite and a running MySQL server. There is no mock-only mode. Setup is in CONTRIBUTING.

## Working Rules

- Add or change tests in `tests/Unit` or `tests/Integration`, and run `composer qa` before finishing.
- Update the PHPDoc and the README when the public API changes.
- Version history lives in `CHANGELOG.md` only. The release checklist lives in CONTRIBUTING.
- Commit messages carry no attribution trailers; the `.githooks/commit-msg` hook strips them (see CONTRIBUTING).

## Documentation

- [README](https://github.com/SilverAssist/wp-settings-hub/blob/main/README.md): install, menu structure, API reference
- [CONTRIBUTING](https://github.com/SilverAssist/wp-settings-hub/blob/main/CONTRIBUTING.md): local setup, tests, quality checks, commit convention, release checklist
- [IMPLEMENTATION](https://github.com/SilverAssist/wp-settings-hub/blob/main/IMPLEMENTATION.md): integration guide for plugin authors
