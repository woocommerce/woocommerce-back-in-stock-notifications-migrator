<?php
/**
 * Load the mapper dependencies without booting WordPress.
 *
 * @package WooCommerce\Back_In_Stock_Notifications_Migrator
 */

require_once dirname( __DIR__, 3 ) . '/vendor/autoload.php';

define( 'ABSPATH', dirname( __DIR__, 3 ) . '/' );

$wc_core_dir = getenv( 'WC_CORE_DIR' );
if ( false === $wc_core_dir || '' === $wc_core_dir ) {
	$wc_core_dir = '/tmp/woocommerce';
}

$enum_path = $wc_core_dir . '/src/Internal/StockNotifications/Enums';
if ( ! is_readable( $enum_path . '/NotificationStatus.php' ) || ! is_readable( $enum_path . '/NotificationCancellationSource.php' ) ) {
	throw new RuntimeException( 'Set WC_CORE_DIR to the WooCommerce plugin directory to load the real notification enums.' );
}

require_once $enum_path . '/NotificationStatus.php';
require_once $enum_path . '/NotificationCancellationSource.php';
