<?php
/**
 * Uninstall handler.
 *
 * @package WooCommerce\Back_In_Stock_Notifications_Migrator
 */

declare( strict_types = 1 );

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Delete this plugin's own run-state and progress options on the current site.
 *
 * These four options are the migration's bookkeeping only: the run state (cursors, cached
 * counts, known losses), the run lock and the batch lock, and the "has anything been
 * migrated" flag. None of them holds migrated data.
 *
 * Deliberately not touched here:
 * - `wc_bis_db_version` — the legacy extension's own marker, not written by this plugin.
 * - `wc_bis_migration_has_legacy_links` — read by WooCommerce Core's `LegacyLinkShim` to
 *   keep answering legacy unsubscribe/verification links from already-delivered emails.
 *   Core reads this flag on every request to decide whether to register the shim, so it
 *   has to outlive this plugin's removal, not just its deactivation.
 * - Core's `wc_stock_notifications` tables and notification meta (including the legacy id,
 *   adopted, unsubscribe-hash and verify-hash markers) — the migrated customer data itself,
 *   which belongs to Core, not to this plugin.
 *
 * @return void
 */
function wc_bis_migrator_delete_site_options(): void {
	delete_option( 'wc_bis_migration_state' );
	delete_option( 'wc_bis_migration_lock' );
	delete_option( 'wc_bis_migration_batch_lock' );
	delete_option( 'wc_bis_migration_has_migrated_rows' );
}

if ( is_multisite() ) {
	$site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $site_ids as $site_id ) {
		switch_to_blog( (int) $site_id );
		wc_bis_migrator_delete_site_options();
		restore_current_blog();
	}
} else {
	wc_bis_migrator_delete_site_options();
}
