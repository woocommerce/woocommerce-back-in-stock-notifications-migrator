# WordPress Playground preview

Every pull request gets a "Test the build" comment. Its third option is a
[WordPress Playground](https://wordpress.org/playground/) link, which opens the pull request's build in
the visitor's browser, with nothing to install.

The comment and the link are built in `.github/workflows/test-the-build.yml`. This directory holds what
the preview needs besides the plugin zip. None of it ships: `.gitattributes` keeps `tests/` out of the zip.

## Why the preview needs sample data

The migrator registers its tool only on a site where Back In Stock Notifications was installed at some
point. That is a paid extension, and a public link must not hand out a copy of it.

The migrator never runs the extension's code, though. It reads three tables, a few options and one
product meta key. So the preview writes those itself:

| File | What it does |
| --- | --- |
| `setup.php` | Runs once, from the Blueprint. Skips the WooCommerce setup wizard, turns on Customer stock notifications, creates four products and writes the sample data below. |
| `preview.php` | Copied into `mu-plugins`. Runs the scheduled actions that are due at the end of each admin page load, and shows a notice that says what the preview holds. |
| `blueprint-local.json` | The same preview for a local checkout. |

`setup.php` creates the legacy tables with `tests/php/Helpers/LegacyStore.php`, the helper the PHPUnit
suite uses, so the legacy schema is written down once. The workflow copies that file into the preview
artifact next to the two above.

The sample data:

- Nine subscribers: three waiting for a restock (one of them a registered customer), one who never
  followed the double opt-in link, one already notified, one who unsubscribed, one cancelled by an admin,
  and two the migration cannot move (a deleted product, an address that is not an email address).
- The extension's settings: double opt-in on, unverified sign-ups deleted after 14 days, and a custom
  subject and heading for the back-in-stock email.
- One product, "Walnut serving board", with sign-ups switched off.

After a migration the Tools screen reports nine subscribers checked and two skipped, and
WooCommerce > Notifications lists seven.

## Why it runs scheduled actions itself

The Tools screen starts the migration in the background, through Action Scheduler. A site in a browser
Playground has no WP-Cron, and cannot send itself the request Action Scheduler uses to start a queue.
Without help, "Start migration" would report "Running now" and never move a row.

`preview.php` fires Action Scheduler's own `action_scheduler_run_queue` hook when an admin page load
ends. With nine rows the migration is done by the time the page that started it has loaded, so a reload
shows the result. The "Running now" and "Paused" states are not something the preview shows.

## What the preview cannot show

- **Back In Stock Notifications running next to the migrator.** The warning about duplicate restock
  emails appears only while that extension is active, and so does the refusal to start while it still has
  emails queued.
- **Real store data.** Nine tidy rows say nothing about a table with a million.
- **Email.** A Playground site cannot send any.
- **Legacy email links.** The sample rows carry no unsubscribe or verification tokens.
- **WP-CLI and multisite.**

## Running it locally

From the root of a checkout, with Node.js 20.18 or newer:

```sh
npx @wp-playground/cli@3.1.64 server --login --blueprint=tests/playground/blueprint-local.json \
	--mount-dir . /wordpress/wp-content/plugins/back-in-stock-notifications-migrator-for-woocommerce
```

This installs WooCommerce, mounts the checkout as the plugin and serves the site at
<http://127.0.0.1:9400>. Open `/wp-admin/admin.php?page=wc-status&tab=tools`; log in as `admin` /
`password` if asked. Code changes apply on the next page load. Stop and start the command to get the
sample data back.

To try a migration against real Back In Stock Notifications data, use a copy of a store that has it.
The preview is not a substitute.

## Safety

Both PHP files do nothing unless the `WC_BIS_MIGRATOR_PLAYGROUND_PREVIEW` constant is defined, and only
the two Blueprints define it. `setup.php` also stops on a site that already has the `wc_bis_db_version`
option, because creating the sample tables drops the legacy ones first.

The link never asks for a credential. Playground's artifact proxy picks a build by branch name, so it
can serve the build of another pull request whose branch has the same name, forks included.

## Changing the sample data

Edit `setup.php`. The numbers in the notice (`preview.php`), in the pull request comment
(`test-the-build.yml`) and in this file are written out by hand: keep them in step.
