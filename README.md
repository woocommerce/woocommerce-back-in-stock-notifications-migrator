# WooCommerce Back In Stock Notifications Migrator

Moves Back In Stock Notifications data into WooCommerce Core's built-in customer stock
notifications, then gets out of the way.

It carries over:

- **Subscribers** — the legacy `wc_bis_notifications` rows, with their statuses, dates,
  cancellation sources and email verification state.
- **Settings** — the extension's options, mapped onto the Core feature's equivalents.
- **Product sign-up flags** — the per-product "sign-ups disabled" meta.

## Requirements

- WooCommerce **11.2** or newer, with the **Customer stock notifications** feature enabled.
- The Back In Stock Notifications extension installed at some point on this site. The plugin
  does nothing on a site that never had it — its tables and options are what there is to read.

## Running a migration

From **WooCommerce → Status → Tools**, or over WP-CLI:

```sh
wp wc bis-migrate status
wp wc bis-migrate run --dry-run
wp wc bis-migrate run
```

`run` is resumable and batched, and both entry points share one run state, so a run started
on the Tools screen can be finished from the CLI and the other way around.

While the legacy extension is still active alongside migrated rows, a restock emails the
customer twice — once from each side. The plugin shows a non-dismissible admin notice until
the extension is deactivated.

## What stays behind in WooCommerce Core

Unsubscribe and verification links from already-delivered legacy emails live in customers'
inboxes indefinitely, so answering them is not this plugin's job. The migration writes a
digest of each legacy token onto the migrated notification, and **WooCommerce Core** answers
the links, in
`Automattic\WooCommerce\Internal\StockNotifications\Compat`:

- `LegacyLinkShim` — handles `bis_unsub` and `bis_ver` requests.
- `LegacyLinkConstants` — the option and meta keys both sides agree on.
- `LegacyHash` — the stored digest format this plugin writes and Core reads.

Deactivating or deleting this plugin does not break those links.

## Development

```sh
composer install
composer run lint
```

The test suite runs against WooCommerce Core's own test framework, so it needs a WooCommerce
monorepo checkout and the WordPress test library:

```sh
WP_TESTS_DIR=/path/to/wordpress-tests-lib \
WC_CORE_DIR=/path/to/woocommerce/plugins/woocommerce \
composer run test
```

`.wp-env.json` maps a WooCommerce monorepo checked out next to this one, so the suite also
runs in Docker without a local WordPress:

```sh
npx wp-env start
npx wp-env run tests-cli --env-cwd=wp-content/plugins/woocommerce-back-in-stock-notifications-migrator \
	-- bash -c 'WP_TESTS_DIR=/wordpress-phpunit \
	WC_CORE_DIR=/var/www/html/wp-content/plugins/woocommerce \
	php /var/www/html/wp-content/plugins/woocommerce/vendor/phpunit/phpunit/phpunit -c phpunit.xml.dist'
```
