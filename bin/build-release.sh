#!/usr/bin/env bash
#
# Builds the installable plugin zip: production Composer dependencies, compiled
# JavaScript, and only files tracked by git minus what .distignore lists.
# Untracked local files (tool caches, logs, editor state) can never be packaged.
#
# Usage: bin/build-release.sh [output-dir]   (default: dist/)

set -euo pipefail

SLUG="social-relay-for-postiz"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="${1:-$ROOT/dist}"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

VERSION="$(grep -E '^\s*\*\s*Version:' "$ROOT/$SLUG.php" | awk '{print $NF}')"
echo "Building $SLUG $VERSION"

# Copy the tracked sources only, minus what never ships.
if ! git -C "$ROOT" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
	echo "Error: run the build from a git checkout (only tracked files are packaged)" >&2
	exit 1
fi
mkdir -p "$WORK/$SLUG"
git -C "$ROOT" ls-files -z | rsync -a --from0 --files-from=- "$ROOT/" "$WORK/$SLUG/"
# Then drop what .distignore lists: "/path" is relative to the plugin root, a bare name matches anywhere.
while IFS= read -r pattern || [ -n "$pattern" ]; do
	pattern="${pattern%%#*}"
	pattern="$(echo "$pattern" | tr -d '[:space:]')"
	[ -z "$pattern" ] && continue
	if [ "${pattern#/}" != "$pattern" ]; then
		rm -rf "${WORK:?}/$SLUG/${pattern#/}"
	else
		find "$WORK/$SLUG" -name "$pattern" -exec rm -rf {} +
	fi
done < "$ROOT/.distignore"

cd "$WORK/$SLUG"

# Production PHP dependencies only.
cp "$ROOT/composer.json" "$ROOT/composer.lock" .
composer install --no-dev --optimize-autoloader --no-interaction --no-progress --quiet
rm -f composer.lock

# Compiled JavaScript from a clean install of the build tools.
cp "$ROOT/package-lock.json" .
npm ci --no-audit --no-fund --silent
npm run build --silent
rm -rf node_modules package-lock.json

# Nothing from development may slip through.
for forbidden in node_modules tests .github phpcs.xml.dist phpstan.neon.dist phpunit.xml.dist .phpunit.result.cache; do
	if [ -e "$forbidden" ]; then
		echo "Error: $forbidden is in the package" >&2
		exit 1
	fi
done
# No hidden files or directories at all (e.g. local tool output).
hidden="$(find . -mindepth 1 -name '.*' -print -quit)"
if [ -n "$hidden" ]; then
	echo "Error: hidden file $hidden is in the package" >&2
	exit 1
fi
test -f build/editor-panel.js && test -f build/settings.js && test -f build/log.js

mkdir -p "$OUT"
cd "$WORK"
rm -f "$OUT/$SLUG.zip"
zip -rq "$OUT/$SLUG.zip" "$SLUG"
echo "Created $OUT/$SLUG.zip ($(du -h "$OUT/$SLUG.zip" | cut -f1))"
