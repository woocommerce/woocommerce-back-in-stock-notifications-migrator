<?php
/**
 * Plugin Name: Back In Stock Notifications Migrator: Playground preview
 * Description: Helps a WordPress Playground site show a build of the migrator. Never part of the plugin zip.
 *
 * The Blueprint copies this file into `mu-plugins`. See README.md in this directory.
 *
 * @package WooCommerce\Back_In_Stock_Notifications_Migrator
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

// Only the preview Blueprints define this constant.
if ( ! defined( 'WC_BIS_MIGRATOR_PLAYGROUND_PREVIEW' ) || ! WC_BIS_MIGRATOR_PLAYGROUND_PREVIEW ) {
	return;
}

add_action( 'shutdown', 'wc_bis_migrator_playground_run_scheduled_actions', 20 );
add_action( 'admin_notices', 'wc_bis_migrator_playground_render_notice' );

/**
 * Run the scheduled actions that are due, at the end of an admin page load.
 *
 * The migration runs in the background through Action Scheduler. A site in a browser
 * Playground has no WP-Cron and cannot send itself the request Action Scheduler uses to
 * start a queue, so nothing would ever run it. This stands in for the WP-Cron tick.
 *
 * @return void
 */
function wc_bis_migrator_playground_run_scheduled_actions(): void {
	if ( ! is_admin() || wp_doing_ajax() || ! has_action( 'action_scheduler_run_queue' ) ) {
		return;
	}

	// phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment -- Action Scheduler's own WP-Cron hook, documented there.
	do_action( 'action_scheduler_run_queue', 'Playground preview' );
}

/**
 * Say what the preview holds and where to go, on the screens the migration is used from.
 *
 * @return void
 */
function wc_bis_migrator_playground_render_notice(): void {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

	if ( null === $screen || ! in_array( $screen->id, array( 'woocommerce_page_wc-status', 'woocommerce_page_wc-customer-stock-notifications' ), true ) ) {
		return;
	}

	$seeded = get_option( 'wc_bis_migrator_playground_preview' );

	if ( ! is_array( $seeded ) ) {
		return;
	}

	$message = sprintf(
		'<strong>Preview of a pull request build.</strong> This store holds sample Back In Stock Notifications data: %1$d subscribers, the extension\'s settings and one product with sign-ups switched off. The extension itself is not installed. Go to <a href="%2$s">Migrate Back In Stock Notifications subscribers</a>, select "Start migration" and confirm the prompt, then reload the page to see the result. Migrated subscribers are listed under <a href="%3$s">WooCommerce &rarr; Notifications</a>.',
		(int) ( $seeded['subscribers'] ?? 0 ),
		esc_url( admin_url( 'admin.php?page=wc-status&tab=tools#tool_start_bis_migration' ) ),
		esc_url( admin_url( 'admin.php?page=wc-customer-stock-notifications' ) )
	);

	wp_admin_notice(
		$message,
		array(
			'id'          => 'wc-bis-migrator-playground-preview',
			'type'        => 'info',
			'dismissible' => false,
		)
	);
}
