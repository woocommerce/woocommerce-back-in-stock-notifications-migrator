#!/usr/bin/env bash
#
# Builds a release-ready zip of the plugin straight from git, via `git archive`.
#
# The plugin is pure PHP with a PSR-4 fallback autoloader (see the plugin file's `is_readable(
# __DIR__ . '/vendor/autoload.php' )` check), so the zip needs no `composer install` step and no
# `vendor/` directory. `.gitattributes` marks every dev-only path (tests/, .github/, etc.) with
# `export-ignore`, so `git archive` already produces a clean tree.
#
# Usage: bin/build-zip.sh [git-ref]
#   git-ref defaults to HEAD.

set -euo pipefail

SLUG="back-in-stock-notifications-migrator-for-woocommerce"
REF="${1:-HEAD}"

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

PLUGIN_FILE="${SLUG}.php"

VERSION=$(git show "${REF}:${PLUGIN_FILE}" | grep -m1 -E '^[[:space:]]*\*[[:space:]]*Version:' | sed -E 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*//' | tr -d '\r')

if [ -z "${VERSION}" ]; then
	echo "Could not read the Version header from ${PLUGIN_FILE} at ${REF}." >&2
	exit 1
fi

mkdir -p dist

OUTPUT="dist/${SLUG}-${VERSION}.zip"

git archive --format=zip --worktree-attributes --prefix="${SLUG}/" -o "${OUTPUT}" "${REF}"

echo "Built ${OUTPUT}"
