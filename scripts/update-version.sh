#!/usr/bin/env bash
#
# Prepares the repository for a release: moves the [Unreleased] CHANGELOG notes under a
# new "## [X.Y.Z] - DATE" heading, fixes the compare links and sets PACKAGE_VERSION.
#
# Usage: scripts/update-version.sh X.Y.Z [--date YYYY-MM-DD] [--dry-run]
#
# It does not commit, tag or push. Run scripts/check-versions.sh --expect X.Y.Z afterwards.
# Refuses to run when [Unreleased] is empty, when X.Y.Z is not newer than the newest
# released version, or when the tag v<X.Y.Z> already exists.
#
# SETTINGS_HUB_ROOT overrides the repository root (used to test the script on a copy).

set -eu

ROOT="${SETTINGS_HUB_ROOT:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
NEW=""
DATE=""
DRY_RUN=0

while [ $# -gt 0 ]; do
	case "$1" in
		--date)
			DATE="${2:-}"
			shift 2
			;;
		--dry-run)
			DRY_RUN=1
			shift
			;;
		-h | --help)
			sed -n '2,12p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'
			exit 0
			;;
		-*)
			echo "Unknown argument: $1" >&2
			exit 2
			;;
		*)
			NEW="$1"
			shift
			;;
	esac
done

die() {
	echo "error: $1" >&2
	exit 1
}

[[ "$NEW" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || die "give the new version as X.Y.Z"
DATE="${DATE:-$(date +%F)}"
[[ "$DATE" =~ ^[0-9]{4}-[0-9]{2}-[0-9]{2}$ ]] || die "--date must be YYYY-MM-DD"

SRC="$ROOT/src/SettingsHub.php"
CHANGELOG="$ROOT/CHANGELOG.md"
[ -f "$SRC" ] && [ -f "$CHANGELOG" ] || die "run it from a wp-settings-hub checkout"

PREV=$(sed -n 's/^## \[\([0-9][0-9]*\.[0-9][0-9]*\.[0-9][0-9]*\)\].*/\1/p' "$CHANGELOG" | head -n 1)
[ -n "$PREV" ] || die "no released version heading found in CHANGELOG.md"

# Version ordering: the new version must sort after the previous one.
NEWEST=$(printf '%s\n%s\n' "$PREV" "$NEW" | sort -t. -k1,1n -k2,2n -k3,3n | tail -n 1)
if [ "$NEW" = "$PREV" ] || [ "$NEWEST" != "$NEW" ]; then
	die "$NEW is not newer than the newest released version $PREV"
fi

if git -C "$ROOT" rev-parse -q --verify "refs/tags/v$NEW" >/dev/null 2>&1; then
	die "tag v$NEW already exists; tags are immutable"
fi

UNRELEASED=$(awk '/^## \[Unreleased\]/ {f=1; next} /^## \[/ {f=0} f && NF {c++} END {print c+0}' "$CHANGELOG")
[ "$UNRELEASED" -gt 0 ] || die "[Unreleased] is empty, there is nothing to release"

REPO_URL=$(sed -n 's|^\[Unreleased\]: \(https://github.com/[^/]*/[^/]*\)/compare/.*|\1|p' "$CHANGELOG" | head -n 1)
[ -n "$REPO_URL" ] || die "no [Unreleased] compare link found at the bottom of CHANGELOG.md"

echo "Release $PREV -> $NEW ($DATE)"
echo "  CHANGELOG.md          [Unreleased] ($UNRELEASED lines) becomes [$NEW] - $DATE"
echo "  CHANGELOG.md links    [Unreleased] -> v$NEW...HEAD, add [$NEW]: v$PREV...v$NEW"
echo "  src/SettingsHub.php   PACKAGE_VERSION -> '$NEW'"

if [ "$DRY_RUN" -eq 1 ]; then
	echo "Dry run: nothing written."
	exit 0
fi

TMP=$(mktemp)
trap 'rm -f "$TMP"' EXIT

NEW="$NEW" PREV="$PREV" DATE="$DATE" REPO_URL="$REPO_URL" perl -ne '
	BEGIN { $new = $ENV{NEW}; $prev = $ENV{PREV}; $date = $ENV{DATE}; $url = $ENV{REPO_URL}; }
	if (/^## \[Unreleased\]\s*$/) {
		print "## [Unreleased]\n\n## [$new] - $date\n";
		next;
	}
	if (/^\[Unreleased\]: /) {
		print "[Unreleased]: $url/compare/v$new...HEAD\n";
		print "[$new]: $url/compare/v$prev...v$new\n";
		next;
	}
	print;
' "$CHANGELOG" >"$TMP"
cat "$TMP" >"$CHANGELOG"

NEW="$NEW" perl -i -pe "s/(PACKAGE_VERSION = ')[^']*(')/\${1}\$ENV{NEW}\${2}/" "$SRC"

echo
"$ROOT/scripts/check-versions.sh" --expect "$NEW" || {
	echo "The result does not pass check-versions.sh; review the diff." >&2
	exit 1
}
echo
echo "Done. Review with git diff, commit, then follow the release checklist in CONTRIBUTING.md."
