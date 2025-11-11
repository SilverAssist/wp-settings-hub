# Contributing to SilverAssist Settings Hub

Thank you for your interest in contributing to the SilverAssist Settings Hub package! This document provides guidelines and instructions for contributing.

## Development Setup

### Prerequisites

- PHP 8.2 or higher
- Composer
- Git

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

3. **Install WordPress Test Suite** (optional, recommended):

   ```bash
   bash scripts/install-wp-tests.sh wordpress_test root 'root' localhost latest true
   ```

   This enables tests to use real WordPress functions via `WP_UnitTestCase`. If not installed, tests automatically fall back to Brain Monkey mocks.

4. **Verify setup**:

   ```bash
   ./scripts/run-quality-checks.sh all
   ```

### Before Committing

Always run quality checks before committing:

```bash
./scripts/run-quality-checks.sh all
```

This will:
1. Auto-fix code standards (PHPCBF)
2. Check code standards (PHPCS)
3. Run static analysis (PHPStan Level 8)
4. Run test suite (PHPUnit)

### Individual Checks

Run specific checks as needed:

```bash
# Auto-fix code standards
composer phpcbf

# Check code standards
composer phpcs

# Run static analysis
composer phpstan

# Run tests
composer phpunit

# Run all checks
composer qa
```

## Coding Standards

### PHP Standards

- **PHP Version**: 8.2+
- **Strict Types**: All files must use `declare(strict_types=1);`
- **Type Hints**: Full type hints for all parameters, return types, and properties
- **WordPress Coding Standards**: Follow WPCS with PSR-4 exceptions
- **PHPStan**: Level 8 compliance required

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

This package requires **WordPress Test Suite** for testing. All tests extend `WP_UnitTestCase` which provides:
- Real WordPress functions (not mocks)
- WordPress database operations
- Factory methods for creating test data
- WordPress-specific assertions

### Installing WordPress Test Suite

**Required before running tests**:

```bash
bash scripts/install-wp-tests.sh wordpress_test root 'root' localhost latest true
```

This script:
- Downloads WordPress core and test suite to `/tmp/wordpress-tests-lib`
- Creates a test database (default: `wordpress_test`)
- Configures the test environment

**Database credentials**: Adjust the script parameters for your MySQL/MariaDB setup:
```bash
bash scripts/install-wp-tests.sh <db-name> <db-user> <db-pass> <db-host> <wp-version> <skip-db-create>
```

### Writing Tests

Tests are located in `tests/Unit/` and extend `SilverAssist\SettingsHub\Tests\TestCase`:

```php
<?php
declare(strict_types=1);

namespace SilverAssist\SettingsHub\Tests\Unit;

use SilverAssist\SettingsHub\Tests\TestCase;
use SilverAssist\SettingsHub\YourClass;

class YourClassTest extends TestCase {
	public function test_something(): void {
		// Use real WordPress functions
		update_option( 'test_key', 'test_value' );
		$value = get_option( 'test_key' );
		
		$instance = new YourClass();
		$result = $instance->method();
		
		$this->assertTrue( $result );
	}
}
```

**Key Points**:
- Extend `SilverAssist\SettingsHub\Tests\TestCase` (which extends `WP_UnitTestCase`)
- WordPress Test Suite must be installed before running tests
- The base class automatically handles WordPress Test Suite or Brain Monkey setup
- When WordPress Test Suite is available, you can use real WordPress functions
- When only Brain Monkey is available, common functions are automatically mocked

### Running Tests

```bash
# Run all tests
composer phpunit

# Run specific test
vendor/bin/phpunit tests/Unit/YourClassTest.php

# Run with coverage
composer test:coverage
```

### Test Coverage

- All new features must include tests
- Aim for 100% coverage of new code
- Tests should be clear and maintainable

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
./scripts/run-quality-checks.sh all

# Ensure all checks pass before proceeding
```

### 4. Commit Guidelines

Follow [Conventional Commits](https://www.conventionalcommits.org/):

```bash
# Format: <type>(<scope>): <subject>

# Examples:
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
- ✅ Pass all CI/CD checks (PHPCS, PHPStan, PHPUnit)
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

### File Structure

```
src/               # Source code
tests/            # Test files
  Unit/           # Unit tests
  Integration/    # Integration tests (if needed)
scripts/          # Automation scripts
.github/          # CI/CD workflows
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

Releases are handled by maintainers:

1. Update version in relevant files
2. Update CHANGELOG.md
3. Create git tag: `v1.2.0`
4. Push tag to trigger release workflow
5. GitHub Actions creates release automatically

## Getting Help

- **Bugs**: Open an [Issue](https://github.com/SilverAssist/wp-settings-hub/issues)
- **Security**: Email security@silverassist.com

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

Thank you for contributing to SilverAssist Settings Hub! 🎉
