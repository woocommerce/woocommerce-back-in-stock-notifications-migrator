<?php
/**
 * PHPUnit bootstrap for the Back In Stock Notifications migrator.
 *
 * The suite runs against WooCommerce Core's own test framework, so it needs a WooCommerce
 * checkout, not just an installed plugin: `WC_CORE_DIR` points at `plugins/woocommerce` in
 * the monorepo, `WP_TESTS_DIR` at the WordPress test library.
 *
 * @package WooCommerce\StockNotificationsMigrator\Tests
 */

declare( strict_types = 1 );

$wp_tests_dir = getenv( 'WP_TESTS_DIR' ) ? getenv( 'WP_TESTS_DIR' ) : '/tmp/wordpress-tests-lib';
$wc_core_dir  = getenv( 'WC_CORE_DIR' ) ? getenv( 'WC_CORE_DIR' ) : '/tmp/woocommerce';
$plugin_dir   = dirname( __DIR__ );

if ( ! is_readable( $wp_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find the WordPress test library at {$wp_tests_dir}. Set WP_TESTS_DIR.\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

if ( ! is_readable( $wc_core_dir . '/woocommerce.php' ) ) {
	echo "Could not find WooCommerce at {$wc_core_dir}. Set WC_CORE_DIR to plugins/woocommerce.\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

require_once $wp_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () use ( $wc_core_dir, $plugin_dir ): void {
		require_once $wc_core_dir . '/woocommerce.php';

		update_option( 'active_plugins', array( 'woocommerce/woocommerce.php' ) );

		require_once $plugin_dir . '/woocommerce-back-in-stock-notifications-migrator.php';
	}
);

tests_add_filter(
	'after_setup_theme',
	static function (): void {
		echo "Installing WooCommerce...\n";
		WC_Install::install();

		// The migration only runs with the stock notifications feature on.
		update_option( 'woocommerce_feature_customer_stock_notifications_enabled', 'yes' );

		$GLOBALS['wp_roles'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- reloading capabilities after install, as WooCommerce's own bootstrap does.
		wp_roles();
	},
	100
);

require_once $wp_tests_dir . '/includes/bootstrap.php';

// WooCommerce's test framework, which the suite's test cases and traits come from.
$wc_tests_dir = is_dir( $wc_core_dir . '/tests/framework' ) ? $wc_core_dir . '/tests' : $wc_core_dir . '/tests/legacy';

require_once $wc_tests_dir . '/includes/wp-http-testcase.php';
require_once $wc_tests_dir . '/framework/class-wc-unit-test-case.php';
require_once $wc_tests_dir . '/framework/class-wc-unit-test-factory.php';
require_once $wc_tests_dir . '/framework/helpers/class-wc-helper-product.php';

// The logger spy trait lives with Core's modern test suite rather than its legacy framework.
$logger_spy = $wc_core_dir . '/tests/php/helpers/LoggerSpyTrait.php';

if ( is_readable( $logger_spy ) ) {
	require_once $logger_spy;
}

require_once __DIR__ . '/php/Mocks/MockWPCLI.php';
require_once __DIR__ . '/php/Mocks/wp-cli-utils.php';

if ( is_readable( dirname( __DIR__ ) . '/vendor/autoload.php' ) ) {
	require_once dirname( __DIR__ ) . '/vendor/autoload.php';
}

spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'Automattic\\WooCommerce\\StockNotificationsMigrator\\Tests\\';

		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}

		$path = __DIR__ . '/php/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

/**
 * Swap WooCommerce's runtime container for the testing one, as Core's own bootstrap does.
 *
 * Without this, `WC_Unit_Test_Case` cannot reset the legacy proxy between tests and no test can
 * replace a service. Reflection is the only way in: the read-only container keeps the real one
 * in a private property.
 */
( static function (): void {
	$inner_container_property = new \ReflectionProperty( \Automattic\WooCommerce\Container::class, 'container' );
	$inner_container_property->setAccessible( true );

	$container       = wc_get_container();
	$inner_container = new \Automattic\WooCommerce\Testing\Tools\TestingContainer( $inner_container_property->getValue( $container ) );

	$inner_container_property->setValue( $container, $inner_container );

	$GLOBALS['wc_container'] = $inner_container;
} )();
