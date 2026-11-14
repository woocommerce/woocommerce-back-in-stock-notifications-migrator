#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."
pnpm run env:test:start
pnpm run wp-env:test run cli wp core is-installed
pnpm run wp-env:test run cli --env-cwd=wp-content/plugins/back-in-stock-notifications-migrator-for-woocommerce \
    composer install --prefer-dist --no-progress --no-interaction
pnpm run wp-env:test run cli --env-cwd=woocommerce-source/plugins/woocommerce bash -ec '
    composer install --prefer-dist --no-progress --no-interaction --ignore-platform-reqs --no-scripts
    mkdir -p includes/react-admin
    php bin/generate-feature-config.php
'
