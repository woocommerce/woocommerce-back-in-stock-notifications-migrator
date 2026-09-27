#!/usr/bin/env bash
#
# Copies a version's entries from changelog.txt into the readme.txt `== Changelog ==` section.
#
# WordPress.org shows the changelog from readme.txt, not changelog.txt, so the release has to
# carry the compiled entries across. Run it after `changelogger write` has compiled the version.
#
# The version's section is placed at the top of `== Changelog ==`. A section that is already
# there for the same version is replaced rather than repeated, so a re-run is harmless.
# `== Upgrade Notice ==` is left alone: it is written by hand.
#
# Usage: bin/sync_readme_changelog.sh <version>

set -euo pipefail

VERSION="${1:-}"

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

README_FILE="readme.txt"
CHANGELOG_FILE="changelog.txt"

fail() {
	echo "$1" >&2
	exit 1
}

[[ "${VERSION}" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || fail "Usage: bin/sync_readme_changelog.sh X.Y.Z"
[ -f "${README_FILE}" ] || fail "${README_FILE} not found."
[ -f "${CHANGELOG_FILE}" ] || fail "${CHANGELOG_FILE} not found."
grep -q '^== Changelog ==' "${README_FILE}" || fail "No '== Changelog ==' section in ${README_FILE}."

# The lines under `YYYY.MM.DD - version X.Y.Z`, up to the blank line that ends the entry.
ENTRIES=$(awk -v version="${VERSION}" '
	found && /^[[:space:]]*$/ { exit }
	found { print }
	# No regex intervals: the mawk that Ubuntu runners ship may not support them.
	split($0, parts, " - version ") == 2 && parts[2] == version && parts[1] ~ /^[0-9][0-9][0-9][0-9]\.[0-9][0-9]\.[0-9][0-9]$/ { found = 1 }
' "${CHANGELOG_FILE}")

[ -n "${ENTRIES}" ] || fail "No entries for version ${VERSION} in ${CHANGELOG_FILE}."

UPDATED=$(mktemp)
trap 'rm -f "${UPDATED}"' EXIT

# Inside `== Changelog ==`: drop an existing `= X.Y.Z =` section for this version, and write
# the new one right after the section heading. Everything outside the section passes through.
ENTRIES="${ENTRIES}" awk -v version="${VERSION}" '
	/^== / {
		in_changelog = ($0 == "== Changelog ==")
		skipping = 0
		print
		if (in_changelog) {
			print ""
			print "= " version " ="
			print ENVIRON["ENTRIES"]
			inserted = 1
			pending_blank = 1
		}
		next
	}
	in_changelog && /^= / {
		skipping = ($0 == "= " version " =")
		if (skipping) next
		print
		pending_blank = 0
		next
	}
	skipping { next }
	# The heading above already printed the blank line that followed it.
	pending_blank && /^[[:space:]]*$/ { print; pending_blank = 0; next }
	{ print }
' "${README_FILE}" > "${UPDATED}"

# Written over rather than moved, so the readme keeps its own permissions.
cat "${UPDATED}" > "${README_FILE}"

echo "Copied the ${VERSION} changelog into ${README_FILE}:"
echo "${ENTRIES}"
