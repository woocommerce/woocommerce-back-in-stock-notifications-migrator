#!/usr/bin/env bash
#
# Asserts that every place the plugin states its version agrees.
#
# WordPress.org serves whatever `Stable tag` points at, so a readme that disagrees with the
# plugin header ships the wrong code, or nothing at all, without failing anywhere else. The
# constant and the changelogs are here because they are the ones that rot silently: nothing
# reads them at release time, so a stale one is only noticed once someone is debugging.
#
# The release workflows read the version from package.json, so it has to agree too.
#
# Usage: bin/check_versions.sh

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

fail() {
	echo "$1" >&2
	exit 1
}

# The main file is named after the WordPress.org slug, not the repository.
SLUG=$(sed -n 's/^[[:space:]]*"wp_org_slug":[[:space:]]*"\([^"]*\)".*/\1/p' package.json | head -n 1)
[ -n "${SLUG}" ] || fail "Could not read config.wp_org_slug from package.json."

PLUGIN_FILE="${SLUG}.php"
README_FILE="readme.txt"
CHANGELOG_FILE="changelog.txt"

[ -f "${PLUGIN_FILE}" ] || fail "${PLUGIN_FILE} not found."
[ -f "${CHANGELOG_FILE}" ] || fail "${CHANGELOG_FILE} not found."

# `"version": "1.2.3"` in package.json.
package_version=$(sed -n 's/^[[:space:]]*"version":[[:space:]]*"\([^"]*\)".*/\1/p' package.json | head -n 1)

# `Version: 1.2.3` in the plugin header.
header_version=$(grep -m1 -E '^[[:space:]]*\*[[:space:]]*Version:' "${PLUGIN_FILE}" |
	sed -E 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*//' | tr -d '\r')

# `define( 'WC_BIS_MIGRATOR_VERSION', '1.2.3' );`
constant_version=$(grep -m1 -E "define\([[:space:]]*'WC_BIS_MIGRATOR_VERSION'" "${PLUGIN_FILE}" |
	sed -E "s/.*'WC_BIS_MIGRATOR_VERSION'[[:space:]]*,[[:space:]]*'([^']+)'.*/\1/" | tr -d '\r')

# `Stable tag: 1.2.3` in the readme header.
stable_tag=$(grep -m1 -E '^Stable tag:' "${README_FILE}" |
	sed -E 's/^Stable tag:[[:space:]]*//' | tr -d '\r')

# The first `= 1.2.3 =` heading after `== Changelog ==` in the readme, i.e. the most recent entry.
readme_changelog_version=$(sed -n '/^== Changelog ==/,$p' "${README_FILE}" |
	grep -m1 -E '^=[[:space:]]*[0-9]' | sed -E 's/^=[[:space:]]*([^[:space:]=]+)[[:space:]]*=.*/\1/' | tr -d '\r')

# The first `YYYY.MM.DD - version 1.2.3` line in changelog.txt.
changelog_line=$(grep -m1 -E '^[0-9]{4}\.[0-9]{2}\.[0-9]{2} - version ' "${CHANGELOG_FILE}" | tr -d '\r' || true)
changelog_version=${changelog_line##* - version }

[ -n "${package_version}" ] || fail "Could not read the version from package.json."
[ -n "${header_version}" ] || fail "Could not read the Version header from ${PLUGIN_FILE}."
[ -n "${constant_version}" ] || fail "Could not read WC_BIS_MIGRATOR_VERSION from ${PLUGIN_FILE}."
[ -n "${stable_tag}" ] || fail "Could not read the Stable tag from ${README_FILE}."
[ -n "${readme_changelog_version}" ] || fail "Could not read the newest changelog entry from ${README_FILE}."
[ -n "${changelog_line}" ] || fail "Could not find a 'YYYY.MM.DD - version X.Y.Z' entry in ${CHANGELOG_FILE}."

echo "package.json:          ${package_version}"
echo "Plugin header:         ${header_version}"
echo "Version constant:      ${constant_version}"
echo "Stable tag:            ${stable_tag}"
echo "readme.txt changelog:  ${readme_changelog_version}"
echo "changelog.txt:         ${changelog_version}"

for version in "${header_version}" "${constant_version}" "${stable_tag}" "${readme_changelog_version}" "${changelog_version}"; do
	if [ "${version}" != "${package_version}" ]; then
		fail "These six must all state the same version. See the values above."
	fi
done

echo "All six agree on ${package_version}."
