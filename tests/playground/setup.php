<?php
/**
 * Prepares a WordPress Playground site for trying a build of the migrator.
 *
 * The Blueprint runs this once, after WooCommerce and the migrator are active. It turns on
 * the Customer stock notifications feature and writes sample Back In Stock Notifications
 * data, so the migration has something to move. The extension itself is never installed:
 * the migrator only reads its tables, options and product meta.
 *
 * See README.md in this directory. Nothing here ships in the plugin zip.
 *
 * @package WooCommerce\Back_In_Stock_Notifications_Migrator
 */

declare( strict_types = 1 );

use Automattic\WooCommerce\StockNotificationsMigrator\Tests\Helpers\LegacyStore;

defined( 'ABSPATH' ) || exit;

/**
 * Write the sample data.
 *
 * Refuses to run anywhere but a preview site, because `LegacyStore::create_tables()` drops
 * the legacy tables before it creates them.
 *
 * @return void
 */
function wc_bis_migrator_playground_setup(): void {
	if ( ! defined( 'WC_BIS_MIGRATOR_PLAYGROUND_PREVIEW' ) || ! WC_BIS_MIGRATOR_PLAYGROUND_PREVIEW ) {
		return;
	}

	// A site that has the legacy extension's data, or that was set up already, is left alone.
	if ( false !== get_option( 'wc_bis_db_version', false ) || false !== get_option( 'wc_bis_migrator_playground_preview', false ) ) {
		return;
	}

	if ( ! class_exists( 'WC_Product_Simple' ) ) {
		return;
	}

	require_once dirname( __DIR__ ) . '/php/Helpers/LegacyStore.php';

	// Skip WooCommerce's setup wizard.
	delete_transient( '_wc_activation_redirect' );
	update_option( 'woocommerce_onboarding_profile', array( 'skipped' => true ) );

	// The migrator registers only while this feature is on.
	update_option( 'woocommerce_feature_customer_stock_notifications_enabled', 'yes' );

	$blanket = wc_bis_migrator_playground_create_product( 'Linen throw blanket', '48', 0 );
	$pitcher = wc_bis_migrator_playground_create_product( 'Stoneware pitcher', '36', 0 );
	$board   = wc_bis_migrator_playground_create_product( 'Walnut serving board', '54', 0 );
	$candle  = wc_bis_migrator_playground_create_product( 'Beeswax candle', '14', 25 );
	$deleted = wc_bis_migrator_playground_create_product( 'Discontinued tea towel', '9', 0 );

	wp_delete_post( $deleted, true );

	// The legacy per-product switch: sign-ups are off for this product.
	update_post_meta( $board, '_wc_bis_disabled', 'yes' );

	$customer = wp_insert_user(
		array(
			'user_login' => 'jamie',
			'user_email' => 'jamie@example.com',
			'user_pass'  => wp_generate_password(),
			'role'       => 'customer',
		)
	);
	$customer = is_wp_error( $customer ) ? 0 : (int) $customer;

	LegacyStore::create_tables();

	$now = time();

	// Waiting for a restock.
	LegacyStore::add_notification(
		array(
			'product_id'     => $blanket,
			'user_email'     => 'alex@example.com',
			'create_date'    => $now - 9 * DAY_IN_SECONDS,
			'subscribe_date' => $now - 9 * DAY_IN_SECONDS,
		)
	);
	LegacyStore::add_notification(
		array(
			'product_id'     => $blanket,
			'user_id'        => $customer,
			'user_email'     => 'jamie@example.com',
			'create_date'    => $now - 6 * DAY_IN_SECONDS,
			'subscribe_date' => $now - 6 * DAY_IN_SECONDS,
		)
	);
	LegacyStore::add_notification(
		array(
			'product_id'     => $pitcher,
			'user_email'     => 'sam@example.com',
			'create_date'    => $now - 3 * DAY_IN_SECONDS,
			'subscribe_date' => $now - 3 * DAY_IN_SECONDS,
		)
	);

	// Signed up under double opt-in and never followed the verification link.
	LegacyStore::add_notification(
		array(
			'product_id'     => $pitcher,
			'user_email'     => 'riley@example.com',
			'create_date'    => $now - DAY_IN_SECONDS,
			'subscribe_date' => 0,
			'is_active'      => 'off',
			'is_verified'    => 'no',
		)
	);

	// Already notified: the product came back in stock.
	LegacyStore::add_notification(
		array(
			'product_id'         => $candle,
			'user_email'         => 'morgan@example.com',
			'create_date'        => $now - 30 * DAY_IN_SECONDS,
			'subscribe_date'     => $now - 30 * DAY_IN_SECONDS,
			'last_notified_date' => $now - 12 * DAY_IN_SECONDS,
			'is_active'          => 'off',
		)
	);

	// Cancelled by the customer.
	$unsubscribed = LegacyStore::add_notification(
		array(
			'product_id'     => $pitcher,
			'user_email'     => 'casey@example.com',
			'create_date'    => $now - 20 * DAY_IN_SECONDS,
			'subscribe_date' => $now - 20 * DAY_IN_SECONDS,
			'is_active'      => 'off',
		)
	);
	LegacyStore::add_activity(
		$unsubscribed,
		'unsubscribed',
		$now - 15 * DAY_IN_SECONDS,
		array(
			'product_id' => $pitcher,
			'user_email' => 'casey@example.com',
		)
	);

	// Cancelled by a store admin.
	$deactivated = LegacyStore::add_notification(
		array(
			'product_id'     => $blanket,
			'user_email'     => 'taylor@example.com',
			'create_date'    => $now - 18 * DAY_IN_SECONDS,
			'subscribe_date' => $now - 18 * DAY_IN_SECONDS,
			'is_active'      => 'off',
		)
	);
	LegacyStore::add_activity(
		$deactivated,
		'deactivated',
		$now - 10 * DAY_IN_SECONDS,
		array(
			'product_id' => $blanket,
			'user_id'    => 1,
			'user_email' => 'taylor@example.com',
		)
	);

	// Two rows the migration cannot move, so the Tools screen has something to report as skipped.
	LegacyStore::add_notification(
		array(
			'product_id'     => $deleted,
			'user_email'     => 'jordan@example.com',
			'create_date'    => $now - 40 * DAY_IN_SECONDS,
			'subscribe_date' => $now - 40 * DAY_IN_SECONDS,
		)
	);
	LegacyStore::add_notification(
		array(
			'product_id'     => $blanket,
			'user_email'     => 'not-an-email-address',
			'create_date'    => $now - 2 * DAY_IN_SECONDS,
			'subscribe_date' => $now - 2 * DAY_IN_SECONDS,
		)
	);

	// The legacy settings, with values that differ from WooCommerce's defaults where it has one.
	update_option( 'wc_bis_allow_signups', 'yes' );
	update_option( 'wc_bis_double_opt_in_required', 'yes' );
	update_option( 'wc_bis_account_required', 'no' );
	update_option( 'wc_bis_delete_unverified_days_threshold', 14 );
	update_option(
		'woocommerce_bis_notification_received_settings',
		array(
			'enabled' => 'yes',
			'subject' => '{product_name} is back at {site_title}',
			'heading' => 'Back on the shelf',
		)
	);

	// What the legacy extension leaves behind on a site it was installed on. The migrator
	// registers its tool only when this option exists.
	update_option( 'wc_bis_db_version', '1.0.0' );

	update_option( 'wc_bis_migrator_playground_preview', array( 'subscribers' => 9 ) );
}

/**
 * Create one published simple product with managed stock.
 *
 * @param string $name  Product name.
 * @param string $price Regular price.
 * @param int    $stock Stock quantity. Zero makes the product out of stock.
 * @return int The product ID.
 */
function wc_bis_migrator_playground_create_product( string $name, string $price, int $stock ): int {
	$product = new WC_Product_Simple();

	$product->set_name( $name );
	$product->set_status( 'publish' );
	$product->set_regular_price( $price );
	$product->set_manage_stock( true );
	$product->set_stock_quantity( $stock );
	$product->set_stock_status( $stock > 0 ? 'instock' : 'outofstock' );

	return $product->save();
}

wc_bis_migrator_playground_setup();
