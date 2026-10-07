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
composer run check:php
```

Pull requests add a change file under `changelog/` instead of editing `changelog.txt`, which
is compiled from them at release time:

```sh
npm run changelog add
```

Label the pull request `no changelog` if the change needs no entry (CI, tooling, docs).

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

`npm run build:zip` builds the release zip into
`deploy/woocommerce-back-in-stock-notifications-migrator.zip`. It is a `git archive` of `HEAD`,
so commit first. The folder inside it is `back-in-stock-notifications-migrator-for-woocommerce`,
the name WordPress installs the plugin under.

## Release

Releases are started with the `Start Release` workflow and shipped by merging the release PR it
creates.

Before starting a release, make sure that:

- Everything you want to ship has been merged into `trunk`, and each of those pull requests left a
  change file under [`changelog/`](https://github.com/woocommerce/woocommerce-back-in-stock-notifications-migrator/tree/trunk/changelog).
- An open [milestone](https://github.com/woocommerce/woocommerce-back-in-stock-notifications-migrator/milestones)
  titled after the version (e.g. `1.0.2`) exists. The workflow refuses to start without one.

To start the release, run the [Start Release workflow](https://github.com/woocommerce/woocommerce-back-in-stock-notifications-migrator/actions/workflows/release-start.yml)
from the Actions tab, or locally with:

```sh
bin/release_start.sh
```

Run it with no arguments to be prompted for the version and the WP/WC "tested up to" values
(press enter to keep the current ones), or pass them directly:
`bin/release_start.sh X.Y.Z --wp A.B --wc C.D`. The script dispatches the workflow, watches it,
and prints the release PR URL when it's done.

On a `release/X.Y.Z` branch, the workflow bumps the version and tested-up-to headers, compiles
the change files under `changelog/` into `changelog.txt` and deletes the ones it consumed, copies
the new entries into the `== Changelog ==` section of `readme.txt`, then opens a pull request
against `trunk`. It also posts a comment on the PR comparing the changelog entries with the
issues in the milestone - review that comment to make sure nothing is missing.

`== Upgrade Notice ==` in `readme.txt` is not generated. Add an entry to the release PR by hand
when the release needs one.

While the release PR is open, `trunk` is under code freeze: the `Check release freeze` check
fails on all other pull requests, and flips back automatically once the release PR is merged or
closed.

A smoke test workflow runs on the release branch ([ci-release-smoke-test.yml](https://github.com/woocommerce/woocommerce-back-in-stock-notifications-migrator/blob/trunk/.github/workflows/ci-release-smoke-test.yml)),
and the release PR goes through the regular PR CI and review like any other PR.

Merging the release PR into `trunk` triggers the release workflow ([ci-release.yml](https://github.com/woocommerce/woocommerce-back-in-stock-notifications-migrator/blob/trunk/.github/workflows/ci-release.yml)),
which builds the zip, tags the version and publishes a GitHub release with the zip attached.
Progress is posted in the `#team-somewherewarm-releases` Slack channel. Tags are bare versions
(`1.0.2`); `v1.0.0` predates this process.

After a successful release, the workflow closes the released milestone and creates one for the
next patch version (rename it if the next release will be a minor/major).

### WordPress.org deploy

A release currently ends at the GitHub release, because the plugin has no WordPress.org slug yet.

Adding `config.wp_org_slug` to `package.json` (the slug WordPress.org grants) and making the
`ORG_DEPLOY_SECRET`, `WPORG_USERNAME` and `WPORG_PASSWORD` secrets available to this repository
turns on the WordPress.org deploy. The release workflow then also pushes the same zip to
WordPress.org through [woo-product-deploy](https://github.com/woocommerce/woo-product-deploy),
with no workflow change.

The zip's top-level folder must equal that slug. It is
`back-in-stock-notifications-migrator-for-woocommerce` today, set by `SLUG` in `bin/build-zip.sh`.
