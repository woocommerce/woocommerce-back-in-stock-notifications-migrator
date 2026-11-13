#!/usr/bin/env bash
#
# Builds a release-ready ZIP from the current checkout or an explicit Git ref.
#
# The plugin is pure PHP with a PSR-4 fallback autoloader (see the plugin file's `is_readable(
# __DIR__ . '/vendor/autoload.php' )` check), so the zip needs no `composer install` step and no
# `vendor/` directory. `.gitattributes` marks every dev-only path (tests/, .github/, etc.) with
# `export-ignore`, so `git archive` already produces a clean tree.
#
# Usage: bin/build-zip.sh [--deploy] [git-ref]
#   Without a ref, archive current tracked file contents. --deploy uses the release ZIP name.

set -euo pipefail

SLUG="back-in-stock-notifications-migrator-for-woocommerce"
DEPLOY=false
if [ "${1:-}" = '--deploy' ]; then
	DEPLOY=true
	shift
fi
REF="${1:-}"

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

PLUGIN_FILE="${SLUG}.php"

read_source() {
	if [[ -n "$REF" ]]; then git show "$REF:$1"; else cat -- "$1"; fi
}

VERSION=$(read_source "$PLUGIN_FILE" | grep -m1 -E '^[[:space:]]*\*[[:space:]]*Version:' | sed -E 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*//' | tr -d '\r')

if [ -z "${VERSION}" ]; then
	echo "Could not read the Version header from ${PLUGIN_FILE} at ${REF:-the working tree}." >&2
	exit 1
fi

if [ "$DEPLOY" = true ]; then
	PACKAGE_VERSION=$(read_source package.json | sed -n 's/^[[:space:]]*"version"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p')
	if [ "$PACKAGE_VERSION" != "$VERSION" ]; then
		echo "The Version header at ${REF:-the working tree} does not match package.json." >&2
		exit 1
	fi
fi

mkdir -p dist
OUTPUT="dist/${SLUG}-${VERSION}.zip"

if [[ -n "$REF" ]]; then
	bash bin/archive-worktree.sh "$OUTPUT" "$SLUG/" "$REF"
else
	bash bin/archive-worktree.sh "$OUTPUT" "$SLUG/"
fi

if [ "$DEPLOY" = true ]; then
	DEPLOY_OUTPUT="deploy/woocommerce-back-in-stock-notifications-migrator.zip"
	mkdir -p deploy
	mv "$OUTPUT" "$DEPLOY_OUTPUT"
	OUTPUT="$DEPLOY_OUTPUT"
fi

echo "Built ${OUTPUT}"
