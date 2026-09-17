<?php
declare( strict_types = 1 );

namespace Automattic\WooCommerce\StockNotificationsMigrator\Tests\Mapping;

use Automattic\WooCommerce\Internal\StockNotifications\Compat\LegacyLinkShim;
use Automattic\WooCommerce\StockNotificationsMigrator\Constants;
use Automattic\WooCommerce\StockNotificationsMigrator\Mapping\LegacyHash;
use WC_Unit_Test_Case;

/**
 * Tests for LegacyHash.
 *
 * The format is a contract with WooCommerce Core's link shim, which reads back what this
 * writes years after the plugin is gone, so these pin the stored shapes themselves rather
 * than round-tripping through the class alone.
 */
class LegacyHashTests extends WC_Unit_Test_Case {

	/**
	 * @before
	 */
	public function setUp(): void {
		parent::setUp();

		if ( ! function_exists( 'wp_fast_hash' ) || ! function_exists( 'wp_verify_fast_hash' ) ) {
			$this->markTestSkipped( 'wp_fast_hash()/wp_verify_fast_hash() require WordPress 6.8 or newer.' );
		}
	}

	/**
	 * @testdox a token without an expiry should be stored as a bare digest.
	 */
	public function test_a_token_without_an_expiry_is_stored_bare(): void {
		$meta_value = LegacyHash::to_meta_value( 'a-legacy-token' );

		$this->assertStringNotContainsString( 'a-legacy-token', $meta_value, 'The raw token must never be stored.' );
		$this->assertTrue( wp_verify_fast_hash( 'a-legacy-token', $meta_value ) );
		$this->assertSame( array( $meta_value, null ), LegacyHash::parse_meta_value( $meta_value ) );
	}

	/**
	 * @testdox a token with an expiry should be stored as `{expires_at}:{digest}`.
	 */
	public function test_a_token_with_an_expiry_carries_it_in_front(): void {
		$expires_at = time() + HOUR_IN_SECONDS;
		$meta_value = LegacyHash::to_meta_value( 'a-legacy-token', $expires_at );
		$parsed     = LegacyHash::parse_meta_value( $meta_value );

		$this->assertSame( $expires_at . ':' . $parsed[0], $meta_value );
		$this->assertSame( $expires_at, $parsed[1] );
		$this->assertTrue( wp_verify_fast_hash( 'a-legacy-token', $parsed[0] ) );
	}

	/**
	 * @testdox verify_token() should match only the token the digest was made from.
	 */
	public function test_verify_token_matches_only_its_own_token(): void {
		$meta_value = LegacyHash::to_meta_value( 'a-legacy-token', time() + HOUR_IN_SECONDS );

		$this->assertTrue( LegacyHash::verify_token( $meta_value, 'a-legacy-token' ) );
		$this->assertFalse( LegacyHash::verify_token( $meta_value, 'another-legacy-token' ) );
	}

	/**
	 * @testdox an expired digest should still verify, since expiry is the caller's to judge.
	 */
	public function test_an_expired_digest_still_verifies(): void {
		$meta_value = LegacyHash::to_meta_value( 'a-legacy-token', time() - HOUR_IN_SECONDS );

		$this->assertTrue( LegacyHash::verify_token( $meta_value, 'a-legacy-token' ) );
		$this->assertLessThan( time(), LegacyHash::parse_meta_value( $meta_value )[1] );
	}

	/**
	 * @testdox a value in neither stored shape should parse as null rather than half-read.
	 */
	public function test_a_malformed_value_parses_as_null(): void {
		foreach ( array( '', ':digest', 'not-a-timestamp:digest', '12345:' ) as $meta_value ) {
			$this->assertNull( LegacyHash::parse_meta_value( $meta_value ), "\"{$meta_value}\" is not a stored shape." );
			$this->assertFalse( LegacyHash::verify_token( $meta_value, 'a-legacy-token' ) );
		}
	}

	/**
	 * @testdox the keys and the format should match Core's shim wherever it is installed.
	 */
	public function test_the_contract_matches_cores_shim(): void {
		if ( ! class_exists( LegacyLinkShim::class ) ) {
			$this->markTestSkipped( 'The installed WooCommerce has no legacy link shim to check against.' );
		}

		$this->assertSame( LegacyLinkShim::HAS_LEGACY_LINKS_OPTION, Constants::HAS_LEGACY_LINKS_OPTION );
		$this->assertSame( LegacyLinkShim::LEGACY_ID_META_KEY_PREFIX, Constants::LEGACY_ID_META_KEY_PREFIX );
		$this->assertSame( LegacyLinkShim::LEGACY_UNSUB_HASH_META_KEY_PREFIX, Constants::LEGACY_UNSUB_HASH_META_KEY_PREFIX );
		$this->assertSame( LegacyLinkShim::LEGACY_VERIFY_HASH_META_KEY_PREFIX, Constants::LEGACY_VERIFY_HASH_META_KEY_PREFIX );

		$expires_at = time() + HOUR_IN_SECONDS;
		$meta_value = LegacyHash::to_meta_value( 'a-legacy-token', $expires_at );

		$this->assertTrue( LegacyLinkShim::verify_token( $meta_value, 'a-legacy-token' ), 'Core must read back a digest this plugin wrote.' );
		$this->assertSame( $expires_at, LegacyLinkShim::parse_meta_value( $meta_value )[1] );
	}
}
