<?php
/**
 * LegacyHash class file.
 *
 * @package WooCommerce\Back_In_Stock_Notifications_Migrator
 */

declare( strict_types = 1 );

namespace Automattic\WooCommerce\StockNotificationsMigrator\Mapping;

defined( 'ABSPATH' ) || exit;

/**
 * The shape of the legacy token digests this migration stores on migrated notifications.
 *
 * The migration writes these; WooCommerce Core's link shim reads them, long after this plugin
 * is gone. The format is defined here, on the writing side, because Core cannot reference a
 * plugin that may not be installed:
 *
 * - an unsubscribe digest is `wp_fast_hash()` of the legacy token, on its own;
 * - a verification digest is the same hash prefixed with the expiry resolved at migration
 *   time, as `{expires_at}:{hash}`, since the legacy link's lifetime is not recoverable from
 *   the migrated row.
 *
 * The raw token is never stored. Expiry is carried, not enforced: whoever answers the link
 * decides what an expired one does.
 *
 * Pure: no database or WordPress hook access, only WordPress' hashing functions.
 */
final class LegacyHash {

	/**
	 * Build the meta value stored for one legacy token.
	 *
	 * @param string   $token      Legacy token, as LegacyToken computed it.
	 * @param int|null $expires_at Expiry timestamp, for a verification token; null for an unsubscribe token, which does not expire.
	 * @return string
	 */
	public static function to_meta_value( string $token, ?int $expires_at = null ): string {
		$hash = wp_fast_hash( $token );

		return null === $expires_at ? $hash : $expires_at . ':' . $hash;
	}

	/**
	 * Split a stored meta value into its hash and its expiry.
	 *
	 * Returns null for a value that is not one of ours, so a caller can leave it alone rather
	 * than act on a half-read one.
	 *
	 * @param string $meta_value Stored meta value.
	 * @return array{0:string,1:int|null}|null The hash and the expiry, or null when the value is malformed.
	 */
	public static function parse_meta_value( string $meta_value ): ?array {
		if ( '' === $meta_value ) {
			return null;
		}

		$separator = strpos( $meta_value, ':' );

		if ( false === $separator ) {
			return array( $meta_value, null );
		}

		$expires_at = substr( $meta_value, 0, $separator );
		$hash       = substr( $meta_value, $separator + 1 );

		if ( '' === $expires_at || ! ctype_digit( $expires_at ) || '' === $hash ) {
			return null;
		}

		return array( $hash, (int) $expires_at );
	}

	/**
	 * Whether a stored meta value is the digest of this token.
	 *
	 * Only the digest is checked. A carried expiry is left to the caller, which knows what
	 * the link it is answering should do about one.
	 *
	 * @param string $meta_value Stored meta value.
	 * @param string $token      Legacy token from the link being answered.
	 * @return bool
	 */
	public static function verify_token( string $meta_value, string $token ): bool {
		$parsed = self::parse_meta_value( $meta_value );

		if ( null === $parsed ) {
			return false;
		}

		return wp_verify_fast_hash( $token, $parsed[0] );
	}
}
