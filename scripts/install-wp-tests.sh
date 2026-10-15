#!/usr/bin/env bash
#
# Thin wrapper, delegates to the shared script in silverassist/wp-coding-standards
# so local dev commands and docs referencing `scripts/install-wp-tests.sh` keep
# working without this repo hand-maintaining its own copy.
#
# Usage:
#   bash scripts/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version] [skip-database-creation]
#
# Example (local MySQL with an empty root password):
#   bash scripts/install-wp-tests.sh wp_settings_hub_test root '' 127.0.0.1 latest
#
# Requires `composer install` first, plus svn and a running MySQL server.
#
# @package SilverAssist\SettingsHub
#
set -e
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SHARED="$SCRIPT_DIR/../vendor/silverassist/wp-coding-standards/scripts/install-wp-tests.sh"

if [ ! -f "$SHARED" ]; then
	echo "Shared install script not found at vendor/silverassist/wp-coding-standards. Run 'composer install' first." >&2
	exit 1
fi

exec bash "$SHARED" "$@"
