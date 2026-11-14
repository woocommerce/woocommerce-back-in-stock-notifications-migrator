#!/usr/bin/env bash
# Called by php-lint.yml after `composer check:php`.
set -euo pipefail

cd "$(dirname "$0")/../.."

# WordPress.org serves whatever `Stable tag` points at, so a readme that disagrees
# with the plugin header ships the wrong code, or nothing at all.
bash bin/check_versions.sh

# PHPCS checks that a text domain is present and correct; only extraction shows
# whether the string survives it. A placeholder that changes between the singular
# and the plural, or a missing translator comment, fails here and nowhere else.
output=$(wp i18n make-pot . /tmp/plugin.pot \
	--slug=back-in-stock-notifications-migrator-for-woocommerce \
	--exclude=tests,vendor,bin,dist 2>&1)
echo "${output}"
if echo "${output}" | grep -qE '^(Warning|Error):'; then
	echo "::error::make-pot reported problems with the translatable strings above."
	exit 1
fi
