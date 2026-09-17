<?php
/**
 * Plugin Name: Back In Stock Notifications Migrator for WooCommerce
 * Plugin URI: https://github.com/woocommerce/woocommerce-back-in-stock-notifications-migrator
 * Description: Moves Back In Stock Notifications data into WooCommerce Core's built-in stock notifications, then gets out of the way.
 * Version: 1.0.0
 * Author: WooCommerce
 * Author URI: https://woocommerce.com/
 * Requires at least: 6.6
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 11.2
 * WC tested up to: 11.2
 * Text Domain: woocommerce-back-in-stock-notifications-migrator
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package WooCommerce\StockNotificationsMigrator
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

define( 'WC_BIS_MIGRATOR_VERSION', '1.0.0' );
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
			esc_html__( 'Back In Stock Notifications Migrator for WooCommerce needs WooCommerce %s or newer. Update WooCommerce to run the migration.', 'woocommerce-back-in-stock-notifications-migrator' ),
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
 * Register the WP-CLI command, on the same hook WooCommerce registers its own.
 */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_hook(
		'after_wp_load',
		array( \Automattic\WooCommerce\StockNotificationsMigrator\Runners\Cli::class, 'register' )
	);
}
