=== Back In Stock Notifications Migrator for WooCommerce ===
Contributors: automattic, woocommerce
Tags: back in stock, stock notifications, waitlist, migration
Requires at least: 6.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Requires Plugins: woocommerce

Moves Back In Stock Notifications subscribers and settings into WooCommerce's built-in customer stock notifications.

== Description ==

WooCommerce 11.2 added customer stock notifications to core. If your store used the Back In Stock Notifications extension before that, this plugin moves your data across so you can retire the extension.

It carries over three things:

* **Subscribers.** Every sign-up the extension recorded, with its status, dates, cancellation source and email verification state.
* **Settings.** The extension's options, mapped onto the equivalent core settings.
* **Per-product sign-up flags.** Products where sign-ups were switched off stay switched off.

The migration runs in batches and can be resumed. If it is stopped, or the site goes down mid-run, the next run picks up where the last one left off. Subscribers already moved stay put.

You can run it from **WooCommerce → Status → Tools**, where it runs in the background, or over WP-CLI with `wp wc bis-migrate`. Both share the same progress, so a run started on the Tools screen can be finished from the command line and the other way around.

= Requirements =

* WooCommerce 11.2 or newer.
* The **Customer stock notifications** feature turned on, under WooCommerce → Settings → Advanced → Features.
* The Back In Stock Notifications extension installed on this site at some point. On a site that never had it, the plugin has nothing to read and does nothing.

= Duplicate emails while both are active =

While the Back In Stock Notifications extension is still active and subscribers have been migrated, a product coming back in stock emails those customers twice: once from the extension and once from WooCommerce. The plugin shows a notice in the WooCommerce admin and on the Plugins screen until the extension is deactivated. It does not deactivate the extension for you.

The right order is: finish the migration, then deactivate the extension.

= Links in old emails keep working =

Emails the extension already sent contain unsubscribe and verification links. Those keep working after the migration, and after this plugin is deactivated or deleted, because WooCommerce itself answers them. The migration records what it needs for that on each migrated subscriber.

= What it does not do =

* It does not delete the extension's tables or options. Your legacy data is left where it was.
* It does not deactivate or uninstall the extension.
* It does not migrate anything on a site that never had the extension installed.

== Installation ==

1. Make sure WooCommerce 11.2 or newer is installed and that **Customer stock notifications** is turned on under WooCommerce → Settings → Advanced → Features.
2. Install and activate this plugin from Plugins → Add New, or upload the zip.
3. Go to **WooCommerce → Status → Tools** and click **Start migration** next to "Migrate Back In Stock Notifications subscribers". The migration runs in the background and the Tools screen shows its progress.
4. When the migration reports that all subscribers have moved, deactivate the Back In Stock Notifications extension.
5. Deactivate and delete this plugin. Nothing it migrated depends on it.

= WP-CLI =

The same migration is available as a WP-CLI command. This is the better choice on large stores.

Check what there is to migrate and where a run stands:

`wp wc bis-migrate status`

Rehearse without writing anything:

`wp wc bis-migrate run --dry-run`

Run the migration:

`wp wc bis-migrate run`

`run` asks for confirmation before it writes. Pass `--yes` to skip the prompt. See `wp help wc bis-migrate run` for the remaining options.

The command only exists when the Customer stock notifications feature is on and the extension has been installed on the site.

== Frequently Asked Questions ==

= Is it safe to run more than once? =

Yes. Subscribers that have already been moved are recognised and skipped, and settings that have already been imported are not written again. Running it a second time on a finished migration does almost nothing.

= What happens to my legacy data? =

Nothing. The extension's tables and options stay exactly where they are. The migration copies from them and writes into WooCommerce's own stock notification tables. If you want the legacy tables gone, that is a separate step for you to take after you have confirmed the migration.

= When can I deactivate the Back In Stock Notifications extension? =

Once the migration reports that every subscriber has moved. The migration can still read the extension's tables after it is deactivated, so nothing is lost by deactivating early, but subscribers who have not been moved yet get no restock email from either side until the migration finishes. Until you deactivate it, migrated customers can receive two emails per restock, and the plugin keeps showing its notice.

= Can I stop a run partway? =

Yes. On the Tools screen the Start button becomes a Stop button while a run is in progress. Stopping does not undo anything: subscribers already moved stay moved, and the next run continues from where this one stopped. A CLI run can be interrupted the same way and resumed by running the command again.

= Why won't it start while the extension has emails queued? =

If the extension still has restock emails waiting to send, the migration refuses to start, because moving those subscribers now could send the same email twice. Let the extension's queue drain, then start again.

= What if I never had the extension installed? =

Then there is nothing for this plugin to do. It looks for the extension's tables and settings, finds none, and adds nothing to the Tools screen or to WP-CLI. You can delete it.

= Do I need to keep this plugin after the migration? =

No. Once the migration is finished and the extension is deactivated, deactivate and delete this plugin. Links in emails the extension sent in the past still work, because WooCommerce answers them, not this plugin.

= Some rows are reported as failed. What now? =

The migration marks a row it cannot move and carries on with the rest, so one bad row does not hold up the others. `wp wc bis-migrate status` shows how many rows are marked failed, and `wp wc bis-migrate run --retry-failed` clears the marks and tries them again. If the same rows keep failing, check WooCommerce → Status → Logs for the reason.

== Changelog ==

= 0.1.0 =
* Initial release. Migrates Back In Stock Notifications subscribers, settings and per-product sign-up flags into WooCommerce's built-in customer stock notifications, from the Tools screen or WP-CLI.

== Upgrade Notice ==

= 0.1.0 =
Initial release.
