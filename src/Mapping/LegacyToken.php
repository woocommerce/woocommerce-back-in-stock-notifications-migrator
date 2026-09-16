<?php
/**
 * LegacyToken class file.
 */

declare( strict_types = 1 );

namespace Automattic\WooCommerce\StockNotificationsMigrator\Mapping;

defined( 'ABSPATH' ) || exit;

/**
 * Reproduces the tokens the legacy Back In Stock Notifications extension put in its email
 * links, so the migration can store a digest Core's link shim will match them against.
 *
 * The digest's stored shape is not defined here: it belongs to the side that reads it, and
 * lives in Core's `Compat\LegacyHash`, which this migration writes through.
 *
 * Pure: no database or WordPress hook access, only the encryption and hashing functions.
 */
final class LegacyToken {

	/**
	 * Reproduce `WC_BIS_Notification_Data::get_hash()` for one legacy notification.
	 *
	 * Returns null when either secret is missing or `openssl_encrypt()` fails, so the
	 * caller can count the row separately rather than treat it as a lost link.
	 *
	 * @param int    $legacy_id           Legacy notification id.
	 * @param int    $product_id          Product id.
	 * @param int    $legacy_create_date  Legacy `create_date`, as an integer timestamp.
	 * @param string $hash_key            Legacy per-notification `_hash_key` meta value.
	 * @param string $hash_iv             Legacy per-notification `_hash_iv` meta value.
	 * @return string|null
	 */
	public static function compute( int $legacy_id, int $product_id, int $legacy_create_date, string $hash_key, string $hash_iv ): ?string {
		if ( '' === $hash_key || '' === $hash_iv ) {
			return null;
		}

		$input     = "{$legacy_id}-{$product_id}-{$legacy_create_date}";
		$encrypted = openssl_encrypt( $input, 'AES-256-CBC', $hash_key, 0, $hash_iv );

		if ( false === $encrypted ) {
			return null;
		}

		return hash( 'sha256', $encrypted );
	}

	/**
	 * Reproduce `WC_BIS_Notification_Data::get_verification_hash()` for one legacy notification.
	 *
	 * Same null-not-error convention as self::compute(): a row missing either secret, or one
	 * whose encryption fails, yields null so the caller can skip it rather than store a
	 * digest no link will ever match.
	 *
	 * @param string $code Legacy per-notification `_verification_code` meta value.
	 * @param string $key  Legacy per-notification `_verification_key` meta value.
	 * @param string $iv   Legacy per-notification `_verification_iv` meta value.
	 * @return string|null
	 */
	public static function compute_verification( string $code, string $key, string $iv ): ?string {
		if ( '' === $code || '' === $key || '' === $iv ) {
			return null;
		}

		$encrypted = openssl_encrypt( $code, 'AES-256-CBC', $key, 0, $iv );

		if ( false === $encrypted ) {
			return null;
		}

		return hash( 'sha256', $encrypted );
	}
}
