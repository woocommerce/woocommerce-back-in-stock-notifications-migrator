#!/usr/bin/env bash
#
# Asserts that every place the plugin states its version agrees.
#
# WordPress.org serves whatever `Stable tag` points at, so a readme that disagrees with the
# plugin header ships the wrong code, or nothing at all, without failing anywhere else. The
# constant and the changelog are here because they are the two that rot silently: nothing
# reads them at release time, so a stale one is only noticed once someone is debugging.
#
# Usage: bin/check-versions.sh

set -euo pipefail

SLUG="back-in-stock-notifications-migrator-for-woocommerce"

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

PLUGIN_FILE="${SLUG}.php"
README_FILE="readme.txt"

fail() {
	echo "$1" >&2
	exit 1
}

# `Version: 1.2.3` in the plugin header.
header_version=$(grep -m1 -E '^[[:space:]]*\*[[:space:]]*Version:' "${PLUGIN_FILE}" |
	sed -E 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*//' | tr -d '\r')

# `define( 'WC_BIS_MIGRATOR_VERSION', '1.2.3' );`
constant_version=$(grep -m1 -E "define\([[:space:]]*'WC_BIS_MIGRATOR_VERSION'" "${PLUGIN_FILE}" |
	sed -E "s/.*'WC_BIS_MIGRATOR_VERSION'[[:space:]]*,[[:space:]]*'([^']+)'.*/\1/" | tr -d '\r')

# `Stable tag: 1.2.3` in the readme header.
stable_tag=$(grep -m1 -E '^Stable tag:' "${README_FILE}" |
	sed -E 's/^Stable tag:[[:space:]]*//' | tr -d '\r')

# The first `= 1.2.3 =` heading after `== Changelog ==`, i.e. the most recent entry.
changelog_version=$(sed -n '/^== Changelog ==/,$p' "${README_FILE}" |
	grep -m1 -E '^=[[:space:]]*[0-9]' | sed -E 's/^=[[:space:]]*([^[:space:]=]+)[[:space:]]*=.*/\1/' | tr -d '\r')

[ -n "${header_version}" ] || fail "Could not read the Version header from ${PLUGIN_FILE}."
[ -n "${constant_version}" ] || fail "Could not read WC_BIS_MIGRATOR_VERSION from ${PLUGIN_FILE}."
[ -n "${stable_tag}" ] || fail "Could not read the Stable tag from ${README_FILE}."
[ -n "${changelog_version}" ] || fail "Could not read the newest changelog entry from ${README_FILE}."

echo "Plugin header:     ${header_version}"
echo "Version constant:  ${constant_version}"
echo "Stable tag:        ${stable_tag}"
echo "Newest changelog:  ${changelog_version}"

if [ "${header_version}" != "${constant_version}" ] ||
	[ "${header_version}" != "${stable_tag}" ] ||
	[ "${header_version}" != "${changelog_version}" ]; then
	fail "These four must all state the same version. See the values above."
fi

echo "All four agree on ${header_version}."
