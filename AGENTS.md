# AGENTS.md — Back In Stock Notifications Migrator for WooCommerce

Guide for coding agents: repository contracts, daily development, and validation. Parent SWW workspace instructions remain authoritative.

## Project map

A one-shot migration plugin: it moves the Back In Stock Notifications extension's subscribers, settings and per-product sign-up flags into WooCommerce Core's customer stock notifications (WooCommerce 11.2+), then the merchant deletes it. It runs from **WooCommerce → Status → Tools** or `wp wc bis-migrate`.

The plugin header requires PHP 7.4+, WordPress 7.0+ (the floor WooCommerce 11.2 itself sets) and WooCommerce 11.2+ (`WC_BIS_MIGRATOR_MIN_WC_VERSION`). Keep syntax compatible with PHP 7.4. The plugin is pure PHP: no Node toolchain, no build step, no `vendor/` in the release.

It is a free WordPress.org plugin, so it differs from the other SWW extensions:

- The slug, main file and text domain are `back-in-stock-notifications-migrator-for-woocommerce`; the repository is `woocommerce-back-in-stock-notifications-migrator`.
- `readme.txt` is the WordPress.org readme. Its `Stable tag`, the header `Version`, `WC_BIS_MIGRATOR_VERSION` and the newest `== Changelog ==` entry must agree; `bin/check-versions.sh` enforces that in CI.
- The release zip is `git archive` output (`bin/build-zip.sh`). Anything that must stay out of it needs an `export-ignore` line in `.gitattributes`.
- Without Composer, the plugin file registers its own PSR-4 fallback autoloader for `src/`.

| Path | Purpose |
| --- | --- |
| `back-in-stock-notifications-migrator-for-woocommerce.php` | Bootstrap, version constants, WooCommerce version/class gates, admin notices, WP-CLI registration. |
| `uninstall.php` | Deletes only this plugin's run-state options on the current site. |
| `src/Constants.php` | Legacy table names, option names and meta keys, shared with Core's `LegacyLinkShim`. |
| `src/Container.php`, `src/Requirements.php` | Service wiring and the checks that decide whether a migration can run. |
| `src/MigrationController.php`, `src/MigrationRun.php`, `src/MigrationState.php` | Run orchestration, locks, cursors and resumable state. |
| `src/Migrators/` | Notifications, options and product-meta migrators. |
| `src/Mapping/` | Status, date and cancellation-source mapping; `LegacyHash`/`LegacyToken` digest legacy email-link tokens. |
| `src/Runners/` | Tools screen entry (`ToolsRegistrar`), WP-CLI (`Cli`) and the `BatchProcessorInterface` runner. |
| `src/Writers/Writer.php` | Writes into Core's `wc_stock_notifications` tables; honours dry runs. |
| `src/Report/Reporter.php` | Run summaries for the Tools screen and CLI. |
| `tests/php/` | PHPUnit suite on WooCommerce Core's own test framework, including `MultisiteTests`. |
| `tools/phpstan/` | Analysis-only stubs for the WooCommerce `Internal` classes the upstream stubs lack. |
| `bin/` | `build-zip.sh` (release zip) and `check-versions.sh` (version agreement). |

## Commands

### Setup and PHP checks

```bash
composer install
composer lint                   # PHPCS (phpcs.xml.dist, WooCommerce-Core)
composer lint:fix               # phpcbf
composer lint:php               # parallel-lint syntax check
composer lint:phpstan           # PHPStan level 5 (phpstan.neon.dist, phpstan-baseline.neon)
composer lint:phpstan:baseline:update
bin/check-versions.sh           # header, constant, Stable tag and changelog agree
```

CI also audits translatable strings with `wp i18n make-pot` (see `.github/workflows/php-lint.yml`) and fails on any warning, such as a missing translator comment or a placeholder that differs between singular and plural.

`tools/phpstan/woocommerce-internal.stub` declares only the `Internal` members the plugin calls, copied from WooCommerce 11.2.0-dev. When code starts using another `Internal` class or method, add it there from the WooCommerce source rather than baselining `class.notFound`.

### Unit tests

The suite boots WooCommerce Core's test framework, so it needs a WooCommerce monorepo checkout and the WordPress test library:

```bash
WP_TESTS_DIR=/path/to/wordpress-tests-lib \
WC_CORE_DIR=/path/to/woocommerce/plugins/woocommerce \
composer test

WP_MULTISITE=1 WP_TESTS_DIR=... WC_CORE_DIR=... composer test   # multisite leg
```

`MultisiteTests` skips itself on single site, so run both legs when a change touches site-scoped state. `.wp-env.json` maps `../woocommerce/plugins/woocommerce`; see `README.md` for the Docker invocation. In a worktree, wp-env mounts the plugin under the worktree's directory name, so adjust `--env-cwd` to match.

CI tests against the `11.2.0-dev` WooCommerce tag on pull requests and WooCommerce `trunk` in the weekly Cron CI.

### Release zip

```bash
bin/build-zip.sh        # dist/back-in-stock-notifications-migrator-for-woocommerce-<version>.zip
unzip -l dist/*.zip     # confirm only the plugin file, readme, LICENSE, uninstall.php and src/
```

Plugin Check runs against this zip, not the repository.

### Changelog

The changelog lives in `readme.txt` under `== Changelog ==`, as `= X.Y.Z =` sections with `* Fix - ...` style lines, plus an `== Upgrade Notice ==` entry per release. A version bump updates the header `Version`, `WC_BIS_MIGRATOR_VERSION`, `Stable tag` and the changelog together. Changes with no user-visible effect (CI, tooling, docs) need no entry.

## Repository compatibility contracts

**Contracts with WooCommerce Core.** The migration writes data that Core keeps using after this plugin is deleted:

- Core's `LegacyLinkShim` (`Automattic\WooCommerce\Internal\StockNotifications\Compat`) answers `bis_unsub` and `bis_ver` links from already-delivered legacy emails. It reads the `wc_bis_migration_has_legacy_links` option and the legacy-token digests this plugin writes as `_wc_bis_legacy_*` notification meta, in the format `Mapping\LegacyHash` defines. Core spells these strings and the format out independently, and the links sit in inboxes indefinitely: never rename the keys or change the digest format.
- `uninstall.php` deliberately leaves `wc_bis_migration_has_legacy_links`, the legacy extension's `wc_bis_db_version` and all migrated rows and meta in place. Keep it that way.
- The plugin depends on WooCommerce `Internal` classes (`StockNotifications`, `StockNotificationsDataStore`, `BatchProcessingController`, `BatchProcessorInterface`). They carry no backwards-compatibility promise, so `wc_bis_migrator_has_required_wc_classes()` gates the boot. Verify against the WooCommerce version being tested, and treat raising `WC_BIS_MIGRATOR_MIN_WC_VERSION` as a breaking change.

**Public surface.** The `wc_bis_migrator_batch_size` filter, the `wc bis-migrate` command and its flags (`--section`, `--batch-size`, `--dry-run`, `--force`, `--retry-failed`, `--max-batches`, `--yes`), the global `wc_bis_migrator_*` functions and the `WC_BIS_MIGRATOR_*` constants.

**Run state.** Runs are batched, resumable and shared between the Tools screen and CLI through the `wc_bis_migration_*` options. A migration interrupted on one plugin version may be resumed on the next, so keep stored state readable across releases. On multisite everything is per site; `--url` selects the site.

## Shared implementation safeguards

These safeguards complement the repository-specific guidance. In the SWW workspace, the parent `AGENTS.md` remains authoritative for approvals, GitHub writes, and Linear workflow. Local instructions do not relax it.

### Compatibility and extension contracts

- Preserve existing public classes, interfaces, functions, methods, constants, signatures, hooks, hook timing, CSS classes, externally consumed file paths, templates, saved markup, and persisted formats. Assume unseen consumers in extensions, themes, and merchant snippets. Treat changes to any exposed surface as high-risk: state what changes, who could consume it, and why it is safe or how consumers can migrate in the PR description. When in doubt, assume the surface is exposed. If that impact cannot be established, stop and flag it for review before changing it.
- Deprecate instead of removing or renaming a public contract in place. Mark the old symbol `@deprecated` and keep it working alongside its replacement for a migration window. Append hook arguments; do not remove or reorder existing ones, or change when or whether a hook fires without assessing consumers. Retire hooks through `do_action_deprecated()` or `apply_filters_deprecated()`.
- Adding a required interface method breaks existing implementers and must be flagged explicitly. Prefer a compatible concrete-class addition, a separate interface, or a default implementation in an existing abstract base where that fits the extension contract. Removing an interface requirement does not make an implementation's extra method invalid, but it changes the contract available to consumers. Assess both callers and implementers.
- Public and protected overrides are contracts, including whether they run. A fast path that skips an overridable method can disable third-party behavior without changing a signature. Preserve those calls or treat the change as breaking.
- Adding or tightening parameter/return types can reject previously accepted values or break subclasses. Check actual inputs, including `null`, `false`, empty values from metadata, and numeric-string IDs; PHP still coerces some scalar values in weak mode. Adding `declare(strict_types=1)` changes scalar checks on calls made from that file. Tightening a comparison to `===` or adding a strict `instanceof` check can also reject values that shipped code accepted.
- Do not add parameter or return types to filter callbacks, or parameter types to action callbacks. Validate values in the body before passing them to typed code. A filter must preserve an unexpected value unchanged rather than discard another extension's customization. Action return values are ignored.
- Registered script/style handles are public contracts, including handles registered incidentally. Preserve old handles during renames as aliases depending on the new handle; do not load the same file twice.
- Guard global and lifecycle dependencies in admin, REST, CLI, cron, AJAX, webhook, and frontend contexts. Do not assume `$post`, `$wp_query`, a session, or a cart exists. Use `function_exists()` / `class_exists()` for optional symbols, `isset()` for variables, and `did_action()` for lifecycle state. Verify that `WC()` and the required component are initialized before dereferencing them.
- Account for multisite storage: site versus network options (`get_option()` / `get_site_option()`), per-site tables, roles, capabilities, and upload paths. For changes that read or write site state, state whether multisite behavior was verified and report when it was not tested.
- Support subdirectory installs, relocated `wp-content`, and reverse proxies. Derive paths and URLs with WordPress APIs such as `plugins_url()`, `plugin_dir_path()`, and `wp_upload_dir()`; preserve the distinction between `home_url()` and `site_url()`.

### Upgrades and persistent data

When a change introduces or alters persistent state:

- Add a stored version and a version-gated migration reachable by existing installs. Fresh-install setup alone is insufficient. Never edit or reuse an already-shipped migration version.
- Make migrations idempotent and batch large updates, using Action Scheduler where appropriate. New code must read both old and new formats until the migration completes, including requests before cron runs. A synchronous update over an unbounded table can time out on a large store.
- Preserve compatibility with the previous release after migration so rolling back does not fatal or corrupt data. Retain readable old formats for a transition period.
- Do not silently change defaults for existing stores; gate new defaults to new installs.
- Preserve cron/action names with queued jobs and stored option/meta keys, or provide a migration and transition path. Do not assume removing code removes stored data; keep its read path or clean it up through a migration that preserves rollback compatibility.

### Core APIs, security, and defensive coding

Use WordPress/WooCommerce APIs and existing repository abstractions before adding helpers. Hand-written replacements can lose HPOS compatibility, filters, caching, and theme overrides.

| Instead of | Use |
| --- | --- |
| `curl_*` or `file_get_contents()` on a URL | `wp_remote_get()`, `wp_remote_post()` |
| Hand-built database queries | `wc_get_orders()`, `wc_get_products()`, `WP_Query`; use `$wpdb->prepare()` when direct SQL is required |
| Direct product or order metadata reads | The relevant WooCommerce CRUD getters and data stores |
| Manual price, decimal, or date formatting | `wc_price()`, `wc_format_decimal()`, `wc_get_price_to_display()`, `date_i18n()` |
| Ad-hoc statics or options used as a cache | Transients, `wp_cache_*`, `WC_Cache_Helper` |
| Custom `wp_cron` plumbing | Action Scheduler |
| Custom regex or `strip_tags()` sanitizing | `wc_clean()`, `sanitize_text_field()`, `wp_kses_post()`, `absint()` |

Build on existing extension points such as `WC_Data`, `WC_Data_Store_WP`, `WC_Settings_Page`, `WC_Integration`, `WC_Email`, `WP_List_Table`, and `WP_REST_Controller`, or the repository's own base classes. Check existing helpers before adding a parallel implementation.

- Validate filter results before indexing or passing them to typed APIs, including filterable WooCommerce helpers such as `wc_get_image_size()`. Use a meaningful supported default; an arbitrary `0`, `''`, or `[]` can break image dimensions, prices, or quantities.
- Check optional methods with `method_exists()` and optional functions/classes with `function_exists()` / `class_exists()` across supported versions. Branch on `is_wp_error()` explicitly; `WP_Error` is truthy. Handle or propagate a returned error rather than silently discarding it. Validate array/object shapes before accessing keys that may be absent; use `isset()` or `array_key_exists()` as appropriate when `null` is meaningful.
- Before changing filter registrations, inspect `has_filter()` and retain the callback and priority. Restore only state this operation changed; cleanup must not remove registrations owned by the caller or restore state on a path that never changed it.
- Require authorization/capability checks such as `current_user_can()` for state changes and appropriate nonce checks (`check_admin_referer()`, `check_ajax_referer()`, `wp_verify_nonce()`) for cookie-authenticated requests. Admin location alone is not protection. REST routes need a real `permission_callback`, never `__return_true` on a write, plus argument `validate_callback` / `sanitize_callback` definitions.
- Apply `wp_unslash()` to slash-escaped WordPress input, such as `$_GET`, `$_POST`, and `$_REQUEST`, before sanitizing with the appropriate API. Do not unslash already-decoded REST/JSON values again. Escape at output for its context (`esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()`); escaping at assignment before concatenation does not protect the final output. Prepare interpolated SQL with `$wpdb->prepare()`; `%i` supports identifiers on WordPress 6.2+, and identifiers still need an allowlist where appropriate.
- Do not pass untrusted input to raw `unserialize()` or allow untrusted objects to be instantiated; use an existing safe decoder where the format requires one. Do not evaluate input as code, construct callables from user input, or allow unrestricted uploads. Use `wp_safe_redirect()` for user-influenced redirects and keep secrets and personal data out of source and logs.
- Avoid unbounded queries/updates, repeated queries in loops, `'posts_per_page' => -1` on unbounded sets, and `meta_query` on an unindexed key over a large table. Keep expensive work off unconditional `init` / `plugins_loaded` paths when it belongs behind a condition, cache, or admin guard. Reuse existing caches, invalidate them on writes, and batch expensive work with a bounded memory footprint.

### Validation and change scope

- Keep changes focused; preserve existing conventions and supported runtimes. Edit the actual source files, not generated output, using the repository's asset map.
- For behavior fixes, reproduce the failure at the relevant test layer and verify the result. Cover the happy path and boundary/error inputs, `null` / `false` / empty values, filtered values, existing data, and affected integrations. Give migrations, capability/nonce checks, and price/quantity calculations particular attention because silent failures are costly.
- Extend an existing test instead of adding a near-duplicate. Use meaningful assertions: a test that only repeats a mock's configured return value, or still passes when the fix is reverted, does not establish the fix. If the repository has no fixture for a layer, report it as untested instead of writing a hollow test.
- Use the repository's existing test frameworks. Run checks appropriate to the changed files and report failures, skipped checks, and untested layers accurately. Documentation changes need command/path and formatting verification, not invented runtime tests.
- Use isolated worktrees and environment ports where the repository supports them. Follow Linear-generated branch names when working from an issue. Keep commits small and stage only intended paths; never bypass hooks to force a commit through.
- Follow the current PR template. Changelog exemptions and milestone automation differ by repository: describe the actual workflow and missing automation rather than inventing checkboxes, changing labels without authorization, or claiming checks passed.

## Contribution and tooling notes

- Follow the parent SWW instructions for Git/Linear work and the repository's `.github/PULL_REQUEST_TEMPLATE.md`. Use the global authenticated `gh` CLI for GitHub operations; commit, push, and draft-PR creation each need their own authorization.
- CI (`.github/workflows/ci.yml`) runs each check only when its inputs change; drafts skip PHPUnit and Plugin Check, and `Required checks` always fails on a draft. Mark the PR ready for review to get the full result.
- The template has no auto-assign-milestone checkbox. Report that missing control instead of inventing a checked box.
- There is no QIT run: the plugin is not a WooCommerce.com marketplace product.
- CodeRabbit reviews pull requests (`.coderabbit.yaml`). Review rules for PHP DocBlock version tags live in `.github/instructions/php.instructions.md`. Ignore missing, incorrect, or placeholder `@version`/`@since` tags in review; continue following the configured coding standards when editing PHP.
