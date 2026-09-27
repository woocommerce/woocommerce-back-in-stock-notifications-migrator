#!/usr/bin/env bash
#
# Builds the release zip straight from git, via `git archive`.
#
# The plugin is pure PHP with a PSR-4 fallback autoloader (see the plugin file's `is_readable(
# __DIR__ . '/vendor/autoload.php' )` check), so the zip needs no `composer install` step and no
# `vendor/` directory. `.gitattributes` marks every dev-only path (tests/, .github/, etc.) with
# `export-ignore`, so `git archive` already produces a clean tree.
#
# The zip is named after the repository (`package.json` "name"), which is what the release
# workflows look for. The folder inside it is named after the WordPress.org slug
# (`config.wp_org_slug`), which is what WordPress.org and the deploy action expect. The two
# differ for this plugin, so neither is derived from the other.
#
# Usage: bin/build.sh [git-ref]
#   git-ref defaults to HEAD.

set -euo pipefail

REF="${1:-HEAD}"

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

PACKAGE_NAME=$(sed -n 's/^[[:space:]]*"name":[[:space:]]*"\([^"]*\)".*/\1/p' package.json | head -n 1)
SLUG=$(sed -n 's/^[[:space:]]*"wp_org_slug":[[:space:]]*"\([^"]*\)".*/\1/p' package.json | head -n 1)

if [ -z "${PACKAGE_NAME}" ] || [ -z "${SLUG}" ]; then
	echo "Could not read \"name\" and \"config.wp_org_slug\" from package.json." >&2
	exit 1
fi

DEPLOY_DIR="deploy"
OUTPUT="${DEPLOY_DIR}/${PACKAGE_NAME}.zip"

mkdir -p "${DEPLOY_DIR}"
rm -f "${OUTPUT}"

git archive --format=zip --worktree-attributes --prefix="${SLUG}/" -o "${OUTPUT}" "${REF}"

echo "Built ${OUTPUT}"
