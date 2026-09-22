# WooCommerce Back In Stock Notifications Migrator

Migrates Back In Stock Notifications data into WooCommerce Core's built-in customer stock
notifications. Run it once, confirm the results, then delete it.

It carries over:

- **Subscribers** — the legacy `wc_bis_notifications` rows, with their statuses, dates,
  cancellation sources and email verification state.
- **Settings** — the extension's options, mapped onto the Core feature's equivalents.
- **Product sign-up flags** — the per-product "sign-ups disabled" meta.

## Requirements

- WooCommerce **11.2** or newer, with the **Customer stock notifications** feature enabled.
- The Back In Stock Notifications extension installed at some point on this site. Its tables
  and options are all there is to read, so on a site that never had it the plugin does nothing.

## Install

Download the zip from the [latest release](https://github.com/woocommerce/woocommerce-back-in-stock-notifications-migrator/releases/latest)
and upload it under **Plugins → Add New → Upload Plugin**, or clone this repository into
`wp-content/plugins/`. No build step: the plugin is plain PHP and ships without a `vendor/`
directory.

## Running a migration

From **WooCommerce → Status → Tools**, or over WP-CLI:

```sh
wp wc bis-migrate status
wp wc bis-migrate run --dry-run
wp wc bis-migrate run
```

`run` is batched and resumable, and both entry points share one run state, so a run started
on the Tools screen can be finished from the CLI and the other way around. `run` asks for
confirmation before it writes; pass `--yes` to skip the prompt, and `--retry-failed` to clear
the marks on rows an earlier run could not move and try them again.

On multisite the migration is per site — every table and option it touches belongs to one
site — so point WP-CLI at the site that holds the legacy data:

```sh
wp wc bis-migrate run --url=shop.example.com
```

Without `--url`, WP-CLI targets the network's main site, and if that site never had the
extension the command is not registered there.

While the legacy extension is still active alongside migrated rows, a restock emails the
customer twice — once from each side. The plugin shows a non-dismissible admin notice until
the extension is deactivated.

## What stays behind in WooCommerce Core

Unsubscribe and verification links from already-delivered legacy emails sit in customers'
inboxes indefinitely, so answering them cannot be this plugin's job. The migration writes a
digest of each legacy token onto the migrated notification, and **WooCommerce Core** answers
the links, from `LegacyLinkShim` in
`Automattic\WooCommerce\Internal\StockNotifications\Compat`, which handles `bis_unsub` and
`bis_ver` requests.

The option and meta keys, and the digest format, are declared on the writing side —
`Constants` and `Mapping\LegacyHash` here — because Core cannot reference a plugin that may
not be installed. Core spells the same strings and format out again; neither side can change
them once links are in inboxes.

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
