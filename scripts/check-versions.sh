#!/usr/bin/env bash
#
# Checks that every place that carries the package version agrees.
#
# Usage: scripts/check-versions.sh [--expect X.Y.Z]
#
# Without --expect it checks the repository against itself (what the PHPUnit test
# test_package_version_matches_changelog also enforces). With --expect X.Y.Z, which the
# release workflow passes with the tag version, it also requires that version to be
# the one everything says. Exits 1 when anything disagrees, so it can gate a release.
#
# Sources of the version:
#   - src/SettingsHub.php        PACKAGE_VERSION (the stylesheet cache-busting version)
#   - CHANGELOG.md               the newest released "## [X.Y.Z]" heading, plus its compare link
#   - composer.json              must NOT carry a "version" field (Packagist reads the git tag)
#
# SETTINGS_HUB_ROOT overrides the repository root (used to test the script on a copy).

set -u

ROOT="${SETTINGS_HUB_ROOT:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
EXPECT=""

while [ $# -gt 0 ]; do
	case "$1" in
		--expect)
			EXPECT="${2:-}"
			shift 2 || true
			;;
		-h | --help)
			sed -n '2,19p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'
			exit 0
			;;
		*)
			echo "Unknown argument: $1" >&2
			exit 2
			;;
	esac
done

if [ -n "$EXPECT" ] && ! [[ "$EXPECT" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
	echo "--expect needs X.Y.Z, got '$EXPECT'" >&2
	exit 2
fi

SRC="$ROOT/src/SettingsHub.php"
CHANGELOG="$ROOT/CHANGELOG.md"
COMPOSER="$ROOT/composer.json"
STATUS=0

ok() { printf '  ok    %s\n' "$1"; }
bad() {
	printf '  FAIL  %s\n' "$1"
	STATUS=1
}
note() { printf '  note  %s\n' "$1"; }

for file in "$SRC" "$CHANGELOG" "$COMPOSER"; do
	if [ ! -f "$file" ]; then
		echo "Missing ${file#"$ROOT"/}" >&2
		exit 2
	fi
done

CONST_VERSION=$(sed -n "s/.*PACKAGE_VERSION = '\([^']*\)'.*/\1/p" "$SRC" | head -n 1)
CHANGELOG_VERSION=$(sed -n 's/^## \[\([0-9][0-9]*\.[0-9][0-9]*\.[0-9][0-9]*\)\].*/\1/p' "$CHANGELOG" | head -n 1)

echo "Version report"
echo "  PACKAGE_VERSION            ${CONST_VERSION:-<not found>}"
echo "  newest CHANGELOG release   ${CHANGELOG_VERSION:-<not found>}"
[ -n "$EXPECT" ] && echo "  expected                   $EXPECT"
echo

[ -n "$CONST_VERSION" ] && ok "PACKAGE_VERSION found" || bad "PACKAGE_VERSION not found in src/SettingsHub.php"
[ -n "$CHANGELOG_VERSION" ] && ok "released heading found" || bad "no '## [X.Y.Z]' heading in CHANGELOG.md"

if [ -n "$CONST_VERSION" ] && [ -n "$CHANGELOG_VERSION" ]; then
	if [ "$CONST_VERSION" = "$CHANGELOG_VERSION" ]; then
		ok "PACKAGE_VERSION equals the newest CHANGELOG release"
	else
		bad "PACKAGE_VERSION ($CONST_VERSION) differs from the newest CHANGELOG release ($CHANGELOG_VERSION)"
	fi
fi

if [ -n "$EXPECT" ]; then
	[ "$CONST_VERSION" = "$EXPECT" ] && ok "PACKAGE_VERSION is $EXPECT" || bad "PACKAGE_VERSION is '$CONST_VERSION', expected $EXPECT"
	[ "$CHANGELOG_VERSION" = "$EXPECT" ] && ok "CHANGELOG.md newest release is $EXPECT" || bad "CHANGELOG.md newest release is '$CHANGELOG_VERSION', expected $EXPECT"
fi

TARGET="${EXPECT:-$CHANGELOG_VERSION}"
if [ -n "$TARGET" ]; then
	if grep -q "^\[$TARGET\]: https://github.com/.*/compare/.*\.\.\.v$TARGET\$" "$CHANGELOG" || grep -q "^\[$TARGET\]: https://github.com/.*/releases/tag/v$TARGET\$" "$CHANGELOG"; then
		ok "compare link for $TARGET exists"
	else
		bad "CHANGELOG.md has no '[$TARGET]: ...' link at the bottom"
	fi

	if grep -q "^\[Unreleased\]: .*/compare/v$TARGET\.\.\.HEAD\$" "$CHANGELOG"; then
		ok "[Unreleased] link starts at v$TARGET"
	else
		bad "[Unreleased] link does not compare v$TARGET...HEAD"
	fi
fi

if grep -q '"version"[[:space:]]*:' "$COMPOSER"; then
	bad "composer.json has a \"version\" field; remove it, Packagist reads the git tag"
else
	ok "composer.json has no \"version\" field"
fi

UNRELEASED=$(awk '/^## \[Unreleased\]/ {f=1; next} /^## \[/ {f=0} f && NF {c++} END {print c+0}' "$CHANGELOG")
if [ "$UNRELEASED" -gt 0 ]; then
	note "[Unreleased] has $UNRELEASED non-empty lines; a release moves them under the new heading"
fi

if git -C "$ROOT" rev-parse --git-dir >/dev/null 2>&1; then
	LATEST_TAG=$(git -C "$ROOT" tag --list 'v[0-9]*' --sort=-v:refname | head -n 1)
	[ -n "$LATEST_TAG" ] && note "latest git tag: $LATEST_TAG"
fi

echo
if [ "$STATUS" -eq 0 ]; then
	echo "All version sources agree."
else
	echo "Version sources disagree."
fi
exit "$STATUS"
