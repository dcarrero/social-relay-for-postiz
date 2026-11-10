#!/usr/bin/env bash
#
# Builds the installable plugin zip: production Composer dependencies, compiled
# JavaScript, and nothing listed in .distignore.
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

# Copy the sources, minus what never ships.
rsync -a --exclude-from="$ROOT/.distignore" --exclude='/vendor' --exclude='/build' --exclude='/dist' "$ROOT/" "$WORK/$SLUG/"

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
test -f build/editor-panel.js && test -f build/settings.js && test -f build/log.js

mkdir -p "$OUT"
cd "$WORK"
rm -f "$OUT/$SLUG.zip"
zip -rq "$OUT/$SLUG.zip" "$SLUG"
echo "Created $OUT/$SLUG.zip ($(du -h "$OUT/$SLUG.zip" | cut -f1))"
