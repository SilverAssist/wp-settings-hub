# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.3.0] - 2026-10-15

### Added

- Design system documented in `docs/design-system.md`: tokens, component rules, contrast ratios, accessibility rules and a guide for consumer plugins ([#49](https://github.com/SilverAssist/wp-settings-hub/issues/49)).
- Playwright suite (`npm run test:e2e`) that runs the dashboard, a plugin page and the empty state in a real wp-admin (WordPress Playground) and checks them against the design system: documented tokens and classes against the CSS, the radius vocabulary, borders and shadows, keyboard focus, reduced motion, the 360px layout and axe (WCAG 2 A and AA).
- `E2E (design system)` workflow, and an npm entry in Dependabot for the test tooling.
- `aria-label` on the tabs navigation and `aria-current="page"` on the active tab.
- Plugin count on the dashboard, and a hint in the empty state that says how plugins appear.
- New design tokens (`--silver-hub-navy`, `--silver-hub-teal`, `--silver-hub-space-*`, `--silver-hub-text-*`, `--silver-hub-radius-input|button|card|pill` and others, see the design system).
- Optional `capability` argument for `register_plugin()`, `manage_options` by default. It decides who gets the plugin's submenu item, dashboard card, tab and page. An invalid value falls back to `manage_options` and is reported with `_doing_it_wrong()`.
- Filters `silverassist_settings_hub_plugin_capability` and `silverassist_settings_hub_dashboard_capability`.

### Changed

- The Dependabot auto-merge workflow is now a thin caller of the shared `SilverAssist/workflows/dependabot-auto-merge@v1` action. The cooldown and approval logic moved there, and the action also refuses pull requests carrying commits that are not verified Dependabot commits.
- Registering a plugin from an `admin_menu` callback after the hub's priority 5 pass now works: the submenu is added immediately. Registering after `admin_menu` has finished is reported with `_doing_it_wrong()`. Registering before or during `admin_menu` is unchanged.
- The menu registration is idempotent: firing `admin_menu` again no longer duplicates the submenu rows.
- A callback action's JavaScript now runs in a click handler that receives `event` (`function(event) {...}`), and the button ids are unique within a dashboard page (a repeated id gets a numeric suffix, for example labels "Run Now" and "RunNow").
- Unserializing the hub now throws a `LogicException` instead of silently producing a second instance.
- `PACKAGE_VERSION`, the stylesheet `ver` used for cache busting, was left at `1.2.0` through the 1.2.1 and 1.2.2 releases, so browsers kept serving the old stylesheet. It now follows the release version, and a test fails when it drifts from the newest released CHANGELOG heading.
- Redesigned the dashboard, tabs and page header with a border-first monochrome style: bordered cards with an icon tile and no lift on hover, pill tabs with a soft-blue active state, a brand-teal (`#2d8ab3`) focus ring, one brand-navy (`#003073`) primary action per card and a neutral monospace version pill.
- Plugin cards render as `<article>` elements labelled by their title. CSS classes are unchanged.
- The pre-1.3 CSS custom properties (`--silver-hub-color-*`, `--silver-hub-spacing-*`, `--silver-hub-font-size-*` and friends) are now aliases of the new tokens. `--silver-hub-color-primary` is now the brand navy `#003073` instead of `#0073aa`.
- Tabs scroll horizontally on one line below 782px instead of wrapping. Card action buttons are 40px high there.
- `get_plugins()` entries and the `silverassist_settings_hub_plugin_actions` `$plugin` payload now always contain `capability`.
- The dashboard cards and the tabs show only the plugins the current user can open, and the Dashboard tab is hidden for users without the dashboard capability. Calling `render_dashboard()` with no logged-in user now renders the empty state.
- A plugin page stops with a 403 when it is rendered for a user who lacks the plugin's capability.
- The `composer.json` description and the class docblock now say the hub adds a top-level Silver Assist menu, not "Settings > Silver Assist".
- Removed the unused `slevomat/coding-standard` dev dependency from `composer.json`. No ruleset loads a Slevomat sniff; the package is still installed as a dependency of `silverassist/wp-coding-standards`.
- The `register_plugin()` `$args` docblock type is now `array<string, mixed>`, and the plugin array shape is a `PluginData` type alias shared by the docblocks of `get_plugins()` and the private renderers. No behaviour changes.
- Static analysis runs on PHPStan 2.x with `reportUnmatchedIgnoredErrors` on and only one path-scoped `ignoreErrors` entry left (the e2e fixture): the WordPress test library and the `ABSPATH` and `WP_PLUGIN_DIR` constants are stubbed in `tests/phpstan/`. `codecov.yml` sets a project coverage target of 96.26% and ignores `tests/` and `vendor/`.

### Fixed

- The stylesheet loaded on any admin page whose hook suffix merely contained `silver-assist`, such as `settings_page_silver-assist-security`. It now loads on `toplevel_page_silver-assist` and on `{prefix}_page_*`, where the prefix is the one WordPress derived from the (possibly translated) parent menu title.
- The dashboard plugin count included plugins the current user cannot open, so it disagreed with the cards shown.
- The version badge used green text on mint (3.00:1), below WCAG AA. It is now a neutral pill.
- Removed a container query that targeted its own container and never matched. The grid now collapses with `minmax(min(100%, 300px), 1fr)`.
- Added a visible keyboard focus ring and `prefers-reduced-motion` support.

### Documentation

- Documented the `capability` argument, its filters and the `option_page_capability_{group}` caveat (README, IMPLEMENTATION.md). Replaced the "Error 2: 403" troubleshooting entry, which wrongly said a missing `capability` caused 403 errors.
- PHPDoc in `src/SettingsHub.php` now describes the registration window, re-registering a slug, the `plugin_file` first-non-empty rule, the action callback contract, and that the hub adds the submenu pages itself.
- Added a "Registration timing" section to IMPLEMENTATION.md (the supported window, the idempotent menu registration, priority 4 as the safest choice, when the `capability` is evaluated), and aligned its examples, "Error 1", the testing checklist and the best practices with it. Priority 4 is described as the safest `admin_menu` priority, not the only one that works with hub versions before 1.3.0.
- Documented the hook suffixes of the Dashboard, the hub submenu pages and a standalone page in the README, with `get_plugin_page_hookname()` as the way to get them. The IMPLEMENTATION.md examples no longer hard-code `silver-assist_page_`, which is wrong on a site that translates the menu title.
- Documented the action button contract in the README (when the callback runs, the `event` argument of the click handler, the slug argument, the button ids, `url` winning over `callback`, unescaped output), and rewrote IMPLEMENTATION.md "Error 4", which described `onclick` attributes the code never printed.
- Qualified the claim that registering a slug twice replaces the first registration: the stored data is replaced, but a submenu page the hub has already built keeps the first registration's callback, title and capability (README, IMPLEMENTATION.md, `integration-guide.php`).
- Added a consolidated "Hooks and filters" table and guidance on safe slug characters to the README.
- Stated in the README that the text domain `silverassist-settings-hub` is not translatable yet: the package ships no translation files and never loads the text domain.
- Fixed the `integration-guide.php` callback example (explicit `event`, escaped output, no AJAX wording where the hub wires nothing), and escaped the JavaScript strings of the update examples with `esc_js()` instead of `esc_html_e()`.

## [1.2.2] - 2026-09-25

### Changed

- Declared `vcs` repositories for the SilverAssist development dependencies (`coding-standards`, `wp-coding-standards`), so contributors and CI resolve them from GitHub instead of Packagist.org.

### Documentation

- Documented installing this package through a Composer `vcs` repository with a GitHub token, instead of Packagist.org (README, "Installing via Composer").

## [1.2.1] - 2026-03-02

### Added

- Version badge displayed next to plugin name in the page header (`<h1>`)
- CSS styles for `.version-badge` inside `.wrap > h1` with pill-shaped design using CSS custom properties

## [1.2.0] - 2026-03-02

### Added

- External CSS stylesheet (`assets/css/settings-hub.css`) replacing all inline styles
- `get_package_asset_url()` method for reliable asset URL resolution across multi-plugin setups
- `plugin_file` parameter in `register_plugin()` for correct vendor path resolution
- CSS custom properties for consistent theming across dashboard components
- Card-based dashboard layout with hover effects and responsive grid
- Empty state styling with centered icon and message
- Unit tests for CSS class usage and absence of inline styles

### Changed

- Dashboard renders using CSS classes instead of inline `style` attributes
- Plugin cards now use `dashicons-admin-plugins` icon inside `<h2>` for proper alignment
- Style enqueuing uses WordPress `wp_enqueue_style()` with fixed handle for automatic deduplication
- Asset URL resolution prioritises `plugin_file` path over `__DIR__` to avoid autoloader bug ([#17](https://github.com/SilverAssist/wp-github-updater/issues/17))

### Fixed

- Styles now load correctly when multiple Silver Assist plugins share the same Composer vendor directory
- PHPStan configuration updated to ignore `ABSPATH` runtime constant

## [1.1.5] - 2026-01-02

### Fixed

- Fixed admin menu icon visibility by using black fill colors in SVG instead of hardcoded colors (#00D1FF and white) that WordPress cannot style correctly
- Updated `shivammathur/setup-php` action to use valid commit SHA `bf6b4fbd49ca58e4608c9c89fba0b8d90bd2a39f` (version 2.35.5) across all workflows
- Fixed workflow failures caused by invalid commit SHA `e6f75134d35483e1d4417d8b7c6f9dc60e40c0b5`

## [1.1.4] - 2025-11-10

### Changed

- **BREAKING**: Removed Brain Monkey dependency - now requires WordPress Test Suite for all tests
- Downgraded PHPUnit from 10.x to 9.6 to match WordPress Core compatibility
- Updated `yoast/phpunit-polyfills` to ^1.1.0 for PHPUnit 9 compatibility
- All tests now use `WP_UnitTestCase` with real WordPress functions instead of mocks
- Updated `phpunit.xml` schema to PHPUnit 9.6 format
- Added `WP_TESTS_DIR` environment variable configuration in `phpunit.xml`
- Updated CI workflows to use native Ubuntu MySQL instead of `actions-setup-mysql`
- Added PHP MySQL extensions (`mysql`, `pdo_mysql`) to CI environment
- Added Subversion installation in CI for WordPress Test Suite setup

### Added

- **Integration Tests**: 8 new integration tests validating complete WordPress integration
  - WordPress admin menu integration testing
  - Dashboard rendering with multiple plugins
  - Tabs navigation system testing
  - Plugin callback execution validation
  - WordPress capabilities checking
  - Admin menu hook registration verification
  - Plugin registration order preservation
  - Dashboard card structure validation
- **CI/CD**: MySQL service and WordPress Test Suite installation in GitHub Actions
  - Automated WordPress Test Suite installation before tests
  - MySQL 8.0 service for database-dependent tests
- `scripts/install-wp-tests.sh` - WordPress Test Suite installation script (from SilverAssist standards)
- `tests/Integration/SettingsHubIntegrationTest.php` - Complete integration test suite
- `tests/TestCase.php` - Base test case extending `WP_UnitTestCase`

### Removed

- Brain Monkey (`brain/monkey`) dependency - no longer needed
- Mockery (`mockery/mockery`) transitive dependency
- `tests/BrainMonkeyTestTrait.php` - replaced by WordPress Test Suite
- Conditional test infrastructure (Brain Monkey fallback removed)

### Fixed

- PHPStan configuration to properly handle `WP_UnitTestCase` inheritance
- Test bootstrap to require WordPress Test Suite installation
- All tests now work with real WordPress environment and database

### Documentation

- Updated README.md with WordPress Test Suite installation instructions
- Updated CONTRIBUTING.md with dual-mode testing explanation (removed Brain Monkey references)
- Added comprehensive test suite documentation showing unit vs integration tests
- Clarified that WordPress Test Suite is required, not optional

### Testing

- **Total**: 18 tests (10 unit + 8 integration), 65 assertions
- All tests use real WordPress functions via WordPress Test Suite
- Tests validate actual WordPress admin menu integration
- Tests use WordPress factories for creating test users
- Tests verify real WordPress capabilities and hooks system

### Technical Details

- Tests now run against actual WordPress installation in `/tmp/wordpress`
- WordPress Test Suite provides full WordPress environment with MySQL database
- Each test runs in isolated transaction (automatic rollback)
- Compatible with WordPress 6.5+ testing infrastructure
- PHPUnit 9.6.29 matches WordPress Core test suite version

## [1.1.3] - 2025-10-11

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

[Unreleased]: https://github.com/SilverAssist/wp-settings-hub/compare/v1.3.0...HEAD
[1.3.0]: https://github.com/SilverAssist/wp-settings-hub/compare/v1.2.2...v1.3.0
[1.2.2]: https://github.com/SilverAssist/wp-settings-hub/compare/v1.2.1...v1.2.2
[1.2.1]: https://github.com/SilverAssist/wp-settings-hub/compare/v1.2.0...v1.2.1
[1.2.0]: https://github.com/SilverAssist/wp-settings-hub/compare/v1.1.5...v1.2.0
[1.1.5]: https://github.com/SilverAssist/wp-settings-hub/compare/v1.1.4...v1.1.5
[1.1.4]: https://github.com/SilverAssist/wp-settings-hub/compare/v1.1.3...v1.1.4
[1.1.3]: https://github.com/SilverAssist/wp-settings-hub/compare/v1.1.2...v1.1.3
[1.1.2]: https://github.com/SilverAssist/wp-settings-hub/compare/v1.1.1...v1.1.2
[1.1.1]: https://github.com/SilverAssist/wp-settings-hub/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/SilverAssist/wp-settings-hub/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/SilverAssist/wp-settings-hub/releases/tag/v1.0.0
