<?php
/**
 * Plugin Name: Back In Stock Notifications Migrator for WooCommerce
 * Plugin URI: https://github.com/woocommerce/woocommerce-back-in-stock-notifications-migrator
 * Description: Moves Back In Stock Notifications data into WooCommerce Core's built-in stock notifications, then gets out of the way.
 * Version: 0.1.0
 * Author: WooCommerce
 * Author URI: https://woocommerce.com/
 * Requires at least: 6.6
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 11.2
 * WC tested up to: 11.2
 * Text Domain: back-in-stock-notifications-migrator-for-woocommerce
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package WooCommerce\StockNotificationsMigrator
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

define( 'WC_BIS_MIGRATOR_VERSION', '0.1.0' );
define( 'WC_BIS_MIGRATOR_FILE', __FILE__ );
define( 'WC_BIS_MIGRATOR_MIN_WC_VERSION', '11.2' );

/**
 * Autoload the plugin's classes.
 *
 * Composer's autoloader when the plugin was installed from source, a plain PSR-4 mapping
 * otherwise, so a checkout dropped into `wp-content/plugins` runs without a build step.
 */
if ( is_readable( __DIR__ . '/vendor/autoload.php' ) ) {
	require __DIR__ . '/vendor/autoload.php';
} else {
	spl_autoload_register(
		static function ( string $class_name ): void {
			$prefix = 'Automattic\\WooCommerce\\StockNotificationsMigrator\\';

			if ( 0 !== strpos( $class_name, $prefix ) ) {
				return;
			}

			$relative = substr( $class_name, strlen( $prefix ) );
			$path     = __DIR__ . '/src/' . str_replace( '\\', '/', $relative ) . '.php';

			if ( is_readable( $path ) ) {
				require $path;
			}
		}
	);
}

/**
 * Boot the migration once WooCommerce is loaded, if this WooCommerce is new enough.
 *
 * The migration writes into Core's `wc_stock_notifications` tables and hands its legacy
 * email links to Core's own shim, both of which landed in WooCommerce 11.2. On anything
 * older there is nothing to migrate into, so the plugin loads and does nothing but say so.
 */
add_action(
	'woocommerce_loaded',
	static function (): void {
		if ( ! defined( 'WC_VERSION' ) || version_compare( WC_VERSION, WC_BIS_MIGRATOR_MIN_WC_VERSION, '<' ) ) {
			add_action( 'admin_notices', 'wc_bis_migrator_render_unsupported_wc_notice' );
			return;
		}

		if ( ! wc_bis_migrator_has_required_wc_classes() ) {
			add_action( 'admin_notices', 'wc_bis_migrator_render_incompatible_wc_notice' );
			return;
		}

		\Automattic\WooCommerce\StockNotificationsMigrator\Container::get(
			\Automattic\WooCommerce\StockNotificationsMigrator\MigrationController::class
		)->register();
	}
);

/**
 * Warn that the installed WooCommerce is too old for this plugin to do anything.
 */
function wc_bis_migrator_render_unsupported_wc_notice(): void {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	wp_admin_notice(
		sprintf(
			/* translators: %s minimum supported WooCommerce version */
			esc_html__( 'Back In Stock Notifications Migrator for WooCommerce needs WooCommerce %s or newer. Update WooCommerce to run the migration.', 'back-in-stock-notifications-migrator-for-woocommerce' ),
			esc_html( WC_BIS_MIGRATOR_MIN_WC_VERSION )
		),
		array(
			'id'          => 'wc-bis-migrator-unsupported-wc',
			'type'        => 'error',
			'dismissible' => false,
		)
	);
}

/**
 * Check that the WooCommerce Core classes the migration binds to are still there.
 *
 * These all live under `Automattic\WooCommerce\Internal\`, which Core is free to rename,
 * move or drop without a deprecation cycle, so a supported `WC_VERSION` is not on its own a
 * promise that they exist. Looking them up first means a WooCommerce that has moved on gets
 * a notice instead of a fatal error on a live store.
 *
 * @return bool
 */
function wc_bis_migrator_has_required_wc_classes(): bool {
	$required = array(
		'Automattic\\WooCommerce\\Internal\\BatchProcessing\\BatchProcessingController',
		'Automattic\\WooCommerce\\Internal\\BatchProcessing\\BatchProcessorInterface',
		'Automattic\\WooCommerce\\Internal\\DataStores\\StockNotifications\\StockNotificationsDataStore',
		'Automattic\\WooCommerce\\Internal\\StockNotifications\\Config',
		'Automattic\\WooCommerce\\Internal\\StockNotifications\\Notification',
		'Automattic\\WooCommerce\\Internal\\StockNotifications\\StockNotifications',
		'Automattic\\WooCommerce\\Internal\\StockNotifications\\Enums\\NotificationCancellationSource',
		'Automattic\\WooCommerce\\Internal\\StockNotifications\\Enums\\NotificationStatus',
	);

	foreach ( $required as $class_name ) {
		if ( ! class_exists( $class_name ) && ! interface_exists( $class_name ) ) {
			return false;
		}
	}

	return true;
}

/**
 * Warn that the installed WooCommerce no longer matches what this plugin migrates into.
 */
function wc_bis_migrator_render_incompatible_wc_notice(): void {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	wp_admin_notice(
		sprintf(
			/* translators: %s: installed WooCommerce version */
			esc_html__( 'Back In Stock Notifications Migrator for WooCommerce is not compatible with WooCommerce %s, so the migration will not run. Your data has not been changed. Check for an update to this plugin, or contact WooCommerce support.', 'back-in-stock-notifications-migrator-for-woocommerce' ),
			esc_html( defined( 'WC_VERSION' ) ? WC_VERSION : '' )
		),
		array(
			'id'          => 'wc-bis-migrator-incompatible-wc',
			'type'        => 'error',
			'dismissible' => false,
		)
	);
}

/**
 * Register the WP-CLI command, on the same hook WooCommerce registers its own.
 *
 * The command touches the same Core classes the admin path does, so it gets the same guard:
 * on a WooCommerce that has moved them, the command is simply not registered, and
 * `wp wc bis-migrate` reports itself as unrecognised. Silently, because this hook runs on
 * every WP-CLI invocation and a warning here would attach itself to unrelated commands.
 */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_hook(
		'after_wp_load',
		static function (): void {
			if ( ! wc_bis_migrator_has_required_wc_classes() ) {
				return;
			}

			\Automattic\WooCommerce\StockNotificationsMigrator\Runners\Cli::register();
		}
	);
}
