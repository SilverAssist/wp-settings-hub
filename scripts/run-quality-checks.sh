#!/usr/bin/env bash
#
# Quality Checks Runner for SilverAssist Settings Hub
#
# This script runs various quality checks for the package.
# It's used both locally and in CI/CD workflows.
#
# Usage:
#   ./scripts/run-quality-checks.sh all           # Run all checks
#   ./scripts/run-quality-checks.sh phpcs         # Run only PHPCS
#   ./scripts/run-quality-checks.sh phpstan       # Run only PHPStan
#   ./scripts/run-quality-checks.sh phpunit       # Run only PHPUnit
#
# @package SilverAssist\SettingsHub
# @since 1.1.4

set -e

# Colors for output.
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Get script directory.
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

# Print colored message.
print_header() {
	echo -e "\n${BLUE}═══════════════════════════════════════════════════════════${NC}"
	echo -e "${BLUE}  $1${NC}"
	echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}\n"
}

print_success() {
	echo -e "${GREEN}✓ $1${NC}"
}

print_error() {
	echo -e "${RED}✗ $1${NC}"
}

print_warning() {
	echo -e "${YELLOW}⚠ $1${NC}"
}

# Run PHP_CodeSniffer auto-fix.
run_phpcbf() {
	print_header "🔧 Running PHPCBF (Auto-fix)"
	
	cd "$PROJECT_ROOT"
	
	# PHPCBF returns 0 if nothing to fix, 1 if files were fixed.
	# We consider both as success.
	if vendor/bin/phpcbf || [ $? -eq 1 ]; then
		print_success "PHPCBF completed"
		return 0
	else
		print_error "PHPCBF failed"
		return 1
	fi
}

# Run PHP_CodeSniffer.
run_phpcs() {
	print_header "🔍 Running PHPCS"
	
	cd "$PROJECT_ROOT"
	
	if vendor/bin/phpcs --warning-severity=0; then
		print_success "PHPCS passed - No errors found"
		return 0
	else
		print_error "PHPCS failed - Code style errors found"
		return 1
	fi
}

# Run PHPStan.
run_phpstan() {
	print_header "🔬 Running PHPStan (Level 8)"
	
	cd "$PROJECT_ROOT"
	
	if php -d memory_limit=1G vendor/bin/phpstan analyse --no-progress; then
		print_success "PHPStan passed - No type errors found"
		return 0
	else
		print_error "PHPStan failed - Type errors found"
		return 1
	fi
}

# Run PHPUnit.
run_phpunit() {
	print_header "🧪 Running PHPUnit Tests"
	
	cd "$PROJECT_ROOT"
	
	if vendor/bin/phpunit; then
		print_success "PHPUnit tests passed"
		return 0
	else
		print_error "PHPUnit tests failed"
		return 1
	fi
}

# Main execution.
main() {
	local failed=0
	
	# Check if vendor directory exists.
	if [ ! -d "$PROJECT_ROOT/vendor" ]; then
		print_error "Vendor directory not found. Run 'composer install' first."
		exit 1
	fi
	
	# If no arguments, show usage.
	if [ $# -eq 0 ]; then
		echo "Usage: $0 [phpcbf|phpcs|phpstan|phpunit|all]"
		echo ""
		echo "Examples:"
		echo "  $0 all           # Run all checks"
		echo "  $0 phpcs         # Run only code standards check"
		echo "  $0 phpstan       # Run only static analysis"
		echo "  $0 phpunit       # Run only tests"
		exit 1
	fi
	
	# Process arguments.
	for arg in "$@"; do
		case $arg in
			phpcbf)
				run_phpcbf || failed=1
				;;
			phpcs)
				run_phpcs || failed=1
				;;
			phpstan)
				run_phpstan || failed=1
				;;
			phpunit)
				run_phpunit || failed=1
				;;
			all)
				# Run all checks in order.
				run_phpcbf || failed=1
				run_phpcs || failed=1
				run_phpstan || failed=1
				run_phpunit || failed=1
				;;
			*)
				print_error "Unknown argument: $arg"
				exit 1
				;;
		esac
	done
	
	# Print summary.
	echo ""
	if [ $failed -eq 0 ]; then
		print_header "✅ All Quality Checks Passed!"
		exit 0
	else
		print_header "❌ Some Quality Checks Failed"
		exit 1
	fi
}

main "$@"
