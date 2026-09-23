<?php
/**
 * Plugin Name: Back In Stock Notifications Migrator for WooCommerce
 * Plugin URI: https://github.com/woocommerce/woocommerce-back-in-stock-notifications-migrator
 * Description: Migrates Back In Stock Notifications subscribers, settings and per-product sign-up flags into WooCommerce's built-in customer stock notifications.
 * Version: 1.0.1
 * Author: WooCommerce
 * Author URI: https://woocommerce.com/
 * Requires at least: 6.8
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 11.2
 * WC tested up to: 11.2
 * Text Domain: back-in-stock-notifications-migrator-for-woocommerce
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package WooCommerce\Back_In_Stock_Notifications_Migrator
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

define( 'WC_BIS_MIGRATOR_VERSION', '1.0.1' );
define( 'WC_BIS_MIGRATOR_FILE', __FILE__ );
define( 'WC_BIS_MIGRATOR_MIN_WC_VERSION', '11.2' );

/**
 * Autoload the plugin's classes.
 *
 * Composer's autoloader when installed from source, a plain PSR-4 mapping otherwise, so a
 * checkout dropped into `wp-content/plugins` runs without a build step.
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
 * Declare compatibility with the WooCommerce features that ask about it.
 *
 * Undeclared, WooCommerce reports the plugin as untested against High-Performance Order
 * Storage on the plugins screen, and as incompatible with the Cart and Checkout blocks in its
 * own compatibility list. Both read to a merchant as warnings about a plugin that is about to
 * write to their database.
 *
 * Both declarations are accurate rather than a way to silence the warnings. The migration
 * never touches an order: it reads the legacy Back In Stock Notifications tables and writes
 * Core's `wc_stock_notifications` ones, so how orders are stored cannot affect it. And it
 * ships no frontend at all - no scripts, styles, shortcodes or blocks, and nothing hooked into
 * the cart or checkout - so it cannot conflict with a block-based one.
 *
 * Declared here rather than inside the `woocommerce_loaded` gate below, because WooCommerce
 * asks on every version that has these features, including the older ones this plugin declines
 * to migrate on - and those are exactly where an unexplained warning is worst.
 */
add_action(
	'before_woocommerce_init',
	static function (): void {
		if ( ! class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			return;
		}

		foreach ( array( 'custom_order_tables', 'cart_checkout_blocks' ) as $feature_id ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( $feature_id, WC_BIS_MIGRATOR_FILE, true );
		}
	}
);

/**
 * Boot the migration once WooCommerce is loaded, if this WooCommerce is new enough.
 *
 * The migration writes into Core's `wc_stock_notifications` tables and hands its legacy
 * email links to Core's own shim, both of which landed in WooCommerce 11.2. On anything
 * older there is nothing to migrate into, so the plugin loads and only says so.
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
			/* translators: %s: minimum supported WooCommerce version */
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
 * Check that the WooCommerce Core classes and constants the migration binds to are there.
 *
 * These live under `Automattic\WooCommerce\Internal\`, which Core may rename, move or drop
 * without a deprecation cycle, so a supported `WC_VERSION` is no promise that they exist.
 * Looking them up first turns a fatal error on a live store into a notice.
 *
 * The constants are checked separately because a class can outlive the API it used to carry:
 * WooCommerce shipped `StockNotifications` before these constants, so `class_exists()` alone
 * answers yes on a version the migration cannot use.
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

	$required_constants = array(
		'Automattic\\WooCommerce\\Internal\\StockNotifications\\StockNotifications::ENABLE_OPTION_NAME',
		'Automattic\\WooCommerce\\Internal\\StockNotifications\\StockNotifications::FEATURE_NAME',
	);

	foreach ( $required_constants as $constant_name ) {
		if ( ! defined( $constant_name ) ) {
			return false;
		}
	}

	return true;
}

/**
 * Whether the installed WooCommerce is one this plugin can migrate into.
 *
 * Both entry points ask this: the Tools screen and WP-CLI write through the same code, so
 * they have to agree on when that code is safe to load at all.
 *
 * @return bool
 */
function wc_bis_migrator_wc_is_supported(): bool {
	if ( ! defined( 'WC_VERSION' ) || version_compare( WC_VERSION, WC_BIS_MIGRATOR_MIN_WC_VERSION, '<' ) ) {
		return false;
	}

	return wc_bis_migrator_has_required_wc_classes();
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
 * The command writes through the same code the admin path does, so it gets the same guard:
 * `Runners\Cli::register()` reads Core class constants as it decides whether to register,
 * which an older WooCommerce carrying the class but not the constant would fatal on. On
 * anything unsupported the command is simply not registered, and `wp wc bis-migrate` reports
 * itself as unrecognized — silently, because this hook runs on every WP-CLI invocation and a
 * warning here would attach itself to unrelated commands.
 */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_hook(
		'after_wp_load',
		static function (): void {
			if ( ! wc_bis_migrator_wc_is_supported() ) {
				return;
			}

			\Automattic\WooCommerce\StockNotificationsMigrator\Runners\Cli::register();
		}
	);
}
