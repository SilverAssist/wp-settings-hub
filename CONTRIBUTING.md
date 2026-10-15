# Contributing to Silver Assist Settings Hub

Thank you for your interest in contributing to the Silver Assist Settings Hub package! This document provides guidelines and instructions for contributing.

## Development Setup

### Prerequisites

- PHP 8.2 or higher, with the extensions CI installs: `mbstring`, `intl`, `mysqli` and `pdo_mysql`
- Composer 2
- Git
- A GitHub token that can read the Silver Assist development dependencies (see below)
- `svn` (Subversion), used by the test-suite installer to download the WordPress test library
- A running MySQL server, plus the `mysql` and `mysqladmin` client tools

The `silverassist/coding-standards` and `silverassist/wp-coding-standards` development dependencies are declared as Composer `vcs` repositories in `composer.json`, and Composer reads them from GitHub. Configure a token before the first `composer install`, as described in the [README installation steps](https://github.com/SilverAssist/wp-settings-hub/blob/main/README.md#installing-via-composer-private-repository) (for example `composer config --global github-oauth.github.com <token>`). CI passes the same token to `composer install` through the `COMPOSER_AUTH` secret. Never commit a token or an `auth.json`.

### Local Setup

1. **Clone the repository**:

   ```bash
   git clone https://github.com/SilverAssist/wp-settings-hub.git
   cd wp-settings-hub
   ```

2. **Install dependencies**:

   ```bash
   composer install
   ```

   This also enables the repository's git hooks (see [Git Hooks](#git-hooks)).

3. **Install the WordPress Test Suite** (required, see [Testing](#testing)):

   ```bash
   bash scripts/install-wp-tests.sh wp_settings_hub_test root '' 127.0.0.1 latest
   ```

4. **Verify setup**:

   ```bash
   vendor/bin/phpunit
   composer qa
   ```

### Before Committing

Always run the quality checks before committing:

```bash
composer qa
```

`composer qa` runs PHPCS, PHPStan (level 8) and the test suite without coverage. `./scripts/run-quality-checks.sh all` runs the same three checks (PHPCS, PHPStan, PHPUnit) and does not auto-fix anything. CI does not auto-fix either, so run `composer phpcbf` yourself first when PHPCS reports fixable errors.

### Individual Checks

Run specific checks as needed:

```bash
# Auto-fix code standards
composer phpcbf

# Check code standards
composer phpcs

# Run static analysis
composer phpstan

# Run tests without coverage
composer test

# Run tests and write the coverage reports
composer phpunit

# Run PHPCS, PHPStan and the tests (no coverage)
composer qa
```

The same checks are available one at a time through `./scripts/run-quality-checks.sh [phpcbf|phpcs|phpstan|phpunit|all]`.

### Git Hooks

`composer install` and `composer update` run the `setup-hooks` script, which sets `core.hooksPath` to `.githooks` in your clone. The `.githooks/commit-msg` hook removes AI attribution trailers from commit messages (for example `Co-Authored-By` lines that name an AI assistant, and "Generated with" footers). It rewrites the message and tells you what it removed, it does not block the commit. Ordinary mentions of a tool name in a commit subject are left alone.

## Coding Standards

### PHP Standards

- **PHP Version**: 8.2+
- **Strict Types**: All files must use `declare(strict_types=1);`
- **Type Hints**: Full type hints for all parameters, return types, and properties
- **Indentation**: Tabs, as in the WordPress coding standards
- **PHPCS**: The `SilverAssistWP` ruleset (see below)
- **PHPStan**: Level 8 compliance required (`src` and `tests` are analysed, PHPStan 2.x). `reportUnmatchedIgnoredErrors` is on, so every entry under `ignoreErrors` in `phpstan.neon` must match an error. Fix the type instead of adding an ignore, and when an ignore is unavoidable scope it with `path` and `identifier`. The WordPress test library is not installed during analysis, so `tests/phpstan/wp-test-suite-stubs.php` stands in for `WP_UnitTestCase`, `tests_add_filter()` and the `ABSPATH` and `WP_PLUGIN_DIR` constants. Shared array shapes live in `@phpstan-type` aliases in the class docblock (`PluginData`, `PluginAction`)

### PHPCS Ruleset

`phpcs.xml` uses the shared `SilverAssistWP` ruleset from the `silverassist/wp-coding-standards` package. That ruleset builds on `WordPress-Extra` and `WordPress-Docs`, and allows PascalCase file names that match the class (PSR-4), K&R braces and short array syntax. `phpcs.xml` adds the `PrefixAllGlobals` rule: globals must start with `silverassist_settings_hub` or live in the `SilverAssist\SettingsHub` namespace. It also sets the minimum supported WordPress version to 6.5 and the PHP compatibility check to 8.2 and up. The `tests/` directory and `integration-guide.php` are excluded from PHPCS.

### Naming Conventions

- **Classes**: `PascalCase`
- **Methods**: `snake_case` (WordPress convention)
- **Properties**: `snake_case`
- **Constants**: `SCREAMING_SNAKE_CASE`
- **Namespaces**: `SilverAssist\SettingsHub`

### Documentation

All code must be fully documented:

```php
/**
 * Class description
 *
 * @package SilverAssist\SettingsHub
 * @since 1.0.0
 */
class ClassName {
	/**
	 * Method description
	 *
	 * @param string $param Parameter description.
	 * @return bool Return value description.
	 */
	public function method_name( string $param ): bool {
		// Implementation.
		return true;
	}
}
```

## Testing

### Test Environment Requirements

The **WordPress Test Suite and a running MySQL server are required**. `tests/bootstrap.php` prints "WordPress Test Suite not found!" and exits with status 1 when the suite is missing, and there is no mock-only mode. Every test extends `SilverAssist\SettingsHub\Tests\TestCase`, which extends `WP_UnitTestCase`, so the `unit` suite boots WordPress and connects to MySQL exactly like the `integration` suite does. The tests therefore use real WordPress functions, the database and the factory methods.

`tests/TestCase.php` also gives every test a clean admin menu state, a fresh style queue and a fresh `SettingsHub` singleton, and offers helpers such as `hub()`, `login_as()`, `fire_admin_menu()` and `render()`.

### Installing the WordPress Test Suite

Run `composer install` first. `scripts/install-wp-tests.sh` is a thin wrapper over the shared installer in `vendor/silverassist/wp-coding-standards`, and it stops with an error if that package is missing.

```bash
bash scripts/install-wp-tests.sh wp_settings_hub_test root '' 127.0.0.1 latest
vendor/bin/phpunit
```

The arguments are:

```bash
bash scripts/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version] [skip-database-creation]
```

- The example uses the database `wp_settings_hub_test`, user `root` with an empty password, host `127.0.0.1` and the latest WordPress. Adjust the user and password to your MySQL setup.
- The script downloads WordPress core and the WordPress test library into `wordpress/` and `wordpress-tests-lib/` under `$TMPDIR` (`/tmp` when `TMPDIR` is unset) and writes `wp-tests-config.php` with your database settings.
- The script creates the database. If it already exists, an interactive run asks before dropping and recreating it, and a non-interactive run (CI) recreates it without asking. Use a database that holds nothing you need.
- The sixth argument, `true`, skips database creation. It is only valid when the database already exists, as in CI, where the MySQL service container creates `wordpress_test` before the script runs. Leave it out locally.
- `WP_TESTS_DIR` is the folder `tests/bootstrap.php` loads the test library from. When the variable is not set it defaults to `<system temp dir>/wordpress-tests-lib`. If the installer put the library somewhere else (for example because `TMPDIR` differs between your shell and PHP), `export WP_TESTS_DIR=/path/to/wordpress-tests-lib` before running PHPUnit. The installer honors the same variable, and also `WP_CORE_DIR` for the WordPress core directory.

### Writing Tests

Unit tests live in `tests/Unit/` and integration tests in `tests/Integration/`. Both extend `SilverAssist\SettingsHub\Tests\TestCase`:

```php
<?php
declare(strict_types=1);

namespace SilverAssist\SettingsHub\Tests\Unit;

use SilverAssist\SettingsHub\Tests\TestCase;

class YourClassTest extends TestCase {
	public function test_something(): void {
		// Use real WordPress functions.
		$this->hub()->register_plugin( 'my-plugin', 'My Plugin', '__return_true' );

		$this->assertTrue( $this->hub()->is_plugin_registered( 'my-plugin' ) );
	}
}
```

**Key Points**:
- Extend `SilverAssist\SettingsHub\Tests\TestCase` (which extends `WP_UnitTestCase`)
- The WordPress Test Suite and MySQL must be available before running any test
- Put a test in `tests/Unit/` when it exercises the hub's methods directly, and in `tests/Integration/` when it goes through the admin menu, page rendering or the enqueue flow
- Name test methods `test_<behaviour>` in `snake_case`

### Running Tests

```bash
# Run all tests (no coverage)
composer test

# Run all tests and write the coverage reports
composer phpunit

# Run one suite (unit, integration or all)
vendor/bin/phpunit --testsuite integration

# Run a specific test file
vendor/bin/phpunit tests/Unit/SettingsHubTest.php

# Run the tests and write the HTML coverage report
composer test:coverage
```

`composer test` runs `phpunit --no-coverage`. `composer phpunit` runs plain `phpunit`, which writes the coverage reports configured in `phpunit.xml` (an HTML report in `coverage/`, `coverage.xml` and a text summary) and needs a coverage driver such as Xdebug or PCOV.

To run the suite in WordPress multisite mode, set `WP_MULTISITE=1`, as the CI multisite job does.

### Design System Tests

Changes to `assets/css/settings-hub.css` or to the markup in `src/SettingsHub.php` must follow [`docs/design-system.md`](docs/design-system.md). Update the token tables in the same commit as the CSS, then run:

```bash
npm install
npx playwright install chromium
npm run test:e2e
```

The suite starts WordPress Playground (WordPress in WebAssembly, so Node 20+ is all it needs) with the fixture plugin in `tests/e2e/fixtures/settings-hub-e2e/`. It fails when a documented token or class is missing from the CSS or has a different value, and it also checks the radius vocabulary, keyboard focus, reduced motion, the 360px layout and accessibility with axe.

### Test Coverage

- All new features must include tests
- Cover the new code, including its failure paths
- Tests should be clear and maintainable

## Continuous Integration

`.github/workflows/ci.yml` runs on pushes to `main` and `develop`, on pull requests to `main`, and on manual dispatch. It calls the reusable `quality-checks.yml` workflow, which starts a MySQL 8.0 service, installs the WordPress Test Suite, then runs `php -l integration-guide.php`, `composer phpcs`, `composer phpstan`, the `unit` suite and the `integration` suite. The jobs are:

- Quality checks on PHP 8.2 (this job also collects coverage and uploads it to Codecov without failing the build, `codecov.yml` sets the project target at 96.26% and ignores `tests/` and `vendor/`), PHP 8.3 and PHP 8.4, all against the latest WordPress
- Quality checks on PHP 8.2 against WordPress 6.5, the oldest supported version
- Quality checks on PHP 8.2 in WordPress multisite mode
- A security audit job that runs `composer audit`
- `.github/workflows/e2e.yml` runs the design system Playwright suite on the same triggers

`auto-merge-dependabot.yml` handles Dependabot updates. Releases are covered in [Release Process](#release-process).

## Pull Request Process

### 1. Fork and Branch

```bash
# Fork the repository on GitHub
# Clone your fork
git clone https://github.com/YOUR_USERNAME/wp-settings-hub.git
cd wp-settings-hub

# Add upstream remote
git remote add upstream https://github.com/SilverAssist/wp-settings-hub.git

# Create a feature branch
git checkout -b feature/your-feature-name
```

### 2. Make Changes

- Write clear, focused commits
- Follow coding standards
- Add tests for new features
- Update documentation as needed

### 3. Test Your Changes

```bash
# Run all quality checks
composer qa

# Ensure all checks pass before proceeding
```

### 4. Commit Guidelines

Recent history uses two styles, and nothing enforces either of them (the only commit hook removes AI attribution trailers, see [Git Hooks](#git-hooks)):

- **Tracked work**: `TICKET: subject`, for example `WEB-1194: Read the vcs repositories with git instead of the GitHub API`
- **Everything else**: a [Conventional Commits](https://www.conventionalcommits.org/) prefix, for example `chore(deps): bump actions/checkout`, `ci: Add auto-merge Dependabot PRs workflow` or `docs: update README installation steps`

Merged commits carry the PR number, such as `(#45)`. Use the ticket style when your change has a ticket, and a Conventional Commits prefix when it does not:

```bash
# Format: <type>(<scope>): <subject>

git commit -m "feat: add new plugin registration option"
git commit -m "fix: resolve PHP 8.4 deprecation warning"
git commit -m "docs: update README installation steps"
git commit -m "test: add coverage for edge cases"
git commit -m "refactor: improve performance of plugin loading"
```

**Types**:
- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation only
- `style`: Code style/formatting
- `refactor`: Code refactoring
- `test`: Adding/updating tests
- `chore`: Maintenance tasks

### 5. Push and Create PR

```bash
# Push to your fork
git push origin feature/your-feature-name

# Create Pull Request on GitHub
```

### 6. PR Requirements

Your PR must:
- ✅ Pass all CI/CD checks (PHPCS, PHPStan, PHPUnit, the Composer security audit)
- ✅ Include tests for new features
- ✅ Update documentation if needed
- ✅ Have a clear description of changes
- ✅ Reference any related issues

### PR Description Template

```markdown
## Description
Brief description of changes

## Type of Change
- [ ] Bug fix
- [ ] New feature
- [ ] Breaking change
- [ ] Documentation update

## Testing
How has this been tested?

## Checklist
- [ ] Code follows project standards
- [ ] All tests pass
- [ ] Documentation updated
- [ ] CHANGELOG.md updated (if applicable)
```

## Code Review Process

1. **Automated Checks**: CI/CD runs automatically
2. **Maintainer Review**: Core team reviews code
3. **Feedback**: Address any requested changes
4. **Approval**: Once approved, PR will be merged

## Reporting Issues

### Bug Reports

When reporting bugs, include:

1. **Description**: Clear description of the bug
2. **Steps to Reproduce**: Detailed steps
3. **Expected Behavior**: What should happen
4. **Actual Behavior**: What actually happens
5. **Environment**:
   - PHP version
   - WordPress version
   - Plugin version

### Feature Requests

For new features:

1. **Use Case**: Why is this needed?
2. **Proposed Solution**: How should it work?
3. **Alternatives**: Other approaches considered?

## Development Guidelines

### Project Layout

```text
src/                         # Source code (SettingsHub.php)
assets/css/                  # settings-hub.css, the stylesheet the hub loads
docs/design-system.md        # Design system: tokens, components, accessibility rules
tests/
  bootstrap.php              # Boots the WordPress Test Suite (exits if it is missing)
  TestCase.php               # Base class for every test (extends WP_UnitTestCase)
  Unit/                      # "unit" suite
  Integration/               # "integration" suite
  e2e/                       # Playwright design system tests and the fixture plugin
  phpstan/                   # PHPStan stubs for the WordPress test library (never loaded by PHPUnit)
package.json                 # End-to-end tooling only (Playwright, WordPress Playground)
playwright.config.ts         # Playwright configuration
scripts/
  install-wp-tests.sh        # Installs the WordPress Test Suite (wrapper over wp-coding-standards)
  run-quality-checks.sh      # Runs PHPCS, PHPStan and PHPUnit
  update-version.sh          # Prepares a release: CHANGELOG heading and links, PACKAGE_VERSION
  check-versions.sh          # Verifies every version source agrees (also run by release.yml)
.githooks/commit-msg         # Strips AI attribution trailers from commit messages
.github/workflows/           # ci.yml, quality-checks.yml, e2e.yml, release.yml, auto-merge-dependabot.yml
README.md                    # Usage and API reference
IMPLEMENTATION.md            # Integration guide for plugin authors
integration-guide.php        # Example plugin integration (excluded from PHPCS)
CHANGELOG.md                 # Version history
```

### Adding New Features

1. **Plan**: Discuss in an issue first
2. **Implement**: Write code following standards
3. **Test**: Add comprehensive tests
4. **Document**: Update README and PHPDoc
5. **PR**: Submit with clear description

### WordPress Integration

When adding WordPress hooks:

```php
// Always escape output
echo esc_html( $text );
echo '<a href="' . esc_url( $url ) . '">';

// Use translation functions
__( 'Text', 'silverassist-settings-hub' );
esc_html_e( 'Text', 'silverassist-settings-hub' );

// Check capabilities
if ( ! current_user_can( 'manage_options' ) ) {
	return;
}
```

## Release Process

Releases are handled by maintainers. This checklist is the canonical copy.

1. **Prepare the release** with the version script, in a release branch:

   ```bash
   bash scripts/update-version.sh X.Y.Z --dry-run   # shows what it will change
   bash scripts/update-version.sh X.Y.Z             # applies it
   bash scripts/check-versions.sh --expect X.Y.Z    # verifies it (update-version.sh also runs this)
   ```

   `update-version.sh` moves the `[Unreleased]` notes under `## [X.Y.Z] - YYYY-MM-DD`, adds the `[X.Y.Z]` compare link, points the `[Unreleased]` link at `vX.Y.Z...HEAD` and sets `PACKAGE_VERSION` in `src/SettingsHub.php`. The hub uses `PACKAGE_VERSION` as the stylesheet cache-busting version, so browsers only fetch a changed `settings-hub.css` when it changes. It refuses an empty `[Unreleased]`, a version that is not newer than the last release, and an existing tag. It does not commit, tag or push.
2. **Review and commit** the result, and open the PR. Before tagging a release that changes the public API, run the suite of a consumer plugin (for example `silver-assist-security`) against the release commit.
3. **Merge to `main`**, then create the tag `vX.Y.Z`:

   ```bash
   git tag -a vX.Y.Z -m "Release vX.Y.Z"
   git push origin vX.Y.Z
   ```

4. **Pushing the tag** triggers `.github/workflows/release.yml` (it can also be started by hand with the `tag` input). The workflow:
   - runs `scripts/check-versions.sh --expect X.Y.Z`, which fails unless `CHANGELOG.md` has the `[X.Y.Z]` heading and compare link and `PACKAGE_VERSION` equals `X.Y.Z`
   - runs the quality checks on PHP 8.3
   - builds a ZIP with `src/`, `assets/`, `vendor/`, `composer.json`, `LICENSE.md`, `README.md` and `CHANGELOG.md`
   - publishes a GitHub release with the changelog section as the release notes

The ZIP does not include `IMPLEMENTATION.md`, `CONTRIBUTING.md` or `integration-guide.php`, so the README links to them with absolute GitHub URLs.

## Getting Help

- **Bugs**: Open an [Issue](https://github.com/SilverAssist/wp-settings-hub/issues)
- **Security**: Email security@silverassist.com
- **Support and questions**: Email support@silverassist.com (the address listed in the README and in `composer.json`)

## License

By contributing, you agree that your contributions will be licensed under the PolyForm Noncommercial License 1.0.0.

## Code of Conduct

### Our Pledge

We are committed to providing a welcoming and inclusive environment for all contributors.

### Expected Behavior

- Be respectful and professional
- Accept constructive criticism gracefully
- Focus on what's best for the project
- Show empathy towards others

### Unacceptable Behavior

- Harassment or discrimination
- Trolling or insulting comments
- Personal or political attacks
- Publishing others' private information

## Recognition

Contributors are recognized in:
- GitHub contributors list
- Release notes (for significant contributions)
- Project documentation (for major features)

---

Thank you for contributing to Silver Assist Settings Hub! 🎉
