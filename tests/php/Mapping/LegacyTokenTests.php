<?php
declare( strict_types = 1 );

namespace Automattic\WooCommerce\StockNotificationsMigrator\Tests\Mapping;

use Automattic\WooCommerce\StockNotificationsMigrator\Mapping\LegacyToken;
use WC_Unit_Test_Case;

/**
 * Tests for LegacyToken.
 *
 * `compute()` must reproduce `WC_BIS_Notification_Data::get_hash()` from the legacy Back
 * In Stock Notifications extension byte for byte, since it is what lets an already-sent
 * unsubscribe link keep working after migration. The stored digest's own format is
 * LegacyHash's, and is tested there.
 */
class LegacyTokenTests extends WC_Unit_Test_Case {

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
	 * @testdox compute() should reproduce the legacy get_hash() token for a fixture row.
	 */
	public function test_compute_matches_legacy_get_hash_fixture(): void {
		// This fixture is reproduced rather than captured: there is no legacy install here
		// to take a real delivered token from. It reimplements
		// `WC_BIS_Notification_Data::get_hash()`'s exact inputs
		// (`{id}-{product_id}-{create_date}`, AES-256-CBC via openssl_encrypt(), then a
		// sha256 digest) independently here, so the test does not simply call
		// LegacyToken::compute() and compare it to itself.
		$legacy_id          = 42;
		$product_id         = 917;
		$legacy_create_date = 1700000000;
		$hash_key           = 'this-is-a-32-byte-legacy-hash-k';
		$hash_iv            = 'legacy-iv-16-byt';

		$expected_input     = "{$legacy_id}-{$product_id}-{$legacy_create_date}";
		$expected_encrypted = openssl_encrypt( $expected_input, 'AES-256-CBC', $hash_key, 0, $hash_iv );
		$expected_token     = hash( 'sha256', $expected_encrypted );

		$result = LegacyToken::compute( $legacy_id, $product_id, $legacy_create_date, $hash_key, $hash_iv );

		$this->assertSame( $expected_token, $result );
	}

	/**
	 * @testdox compute() should return null when the hash key secret is missing.
	 */
	public function test_compute_returns_null_when_key_missing(): void {
		$result = LegacyToken::compute( 42, 917, 1700000000, '', 'legacy-iv-16-byt' );

		$this->assertNull( $result );
	}

	/**
	 * @testdox compute() should return null when the hash iv secret is missing.
	 */
	public function test_compute_returns_null_when_iv_missing(): void {
		$result = LegacyToken::compute( 42, 917, 1700000000, 'this-is-a-32-byte-legacy-hash-k', '' );

		$this->assertNull( $result );
	}

	/**
	 * @testdox compute() should return null when both secrets are missing.
	 */
	public function test_compute_returns_null_when_both_secrets_missing(): void {
		$this->assertNull( LegacyToken::compute( 42, 917, 1700000000, '', '' ) );
	}

	/**
	 * @testdox compute_verification() should reproduce the legacy get_verification_hash() token for a fixture row.
	 */
	public function test_compute_verification_matches_legacy_fixture(): void {
		// Reproduced, not captured, for the same reason as the get_hash() fixture above:
		// `WC_BIS_Notification_Data::get_verification_hash()` encrypts the verification code
		// with AES-256-CBC under the row's own `_verification_key`/`_verification_iv` and
		// takes a sha256 digest of the ciphertext.
		$code = 'verification-code-fixture';
		$key  = 'this-is-a-32-byte-legacy-hash-k';
		$iv   = 'legacy-iv-16-byt';

		$expected_token = hash( 'sha256', openssl_encrypt( $code, 'AES-256-CBC', $key, 0, $iv ) );

		$this->assertSame( $expected_token, LegacyToken::compute_verification( $code, $key, $iv ) );
	}

	/**
	 * @testdox compute_verification() should return null when any of its inputs is empty.
	 * @dataProvider provider_incomplete_verification_secrets
	 *
	 * @param string $code Test case value.
	 * @param string $key  Test case value.
	 * @param string $iv   Test case value.
	 */
	public function test_compute_verification_returns_null_on_incomplete_input( string $code, string $key, string $iv ): void {
		$this->assertNull( LegacyToken::compute_verification( $code, $key, $iv ) );
	}

	/**
	 * Verification triples with at least one missing member.
	 *
	 * @return array
	 */
	public function provider_incomplete_verification_secrets(): array {
		$code = 'verification-code-fixture';
		$key  = 'this-is-a-32-byte-legacy-hash-k';
		$iv   = 'legacy-iv-16-byt';

		return array(
			'code missing' => array( '', $key, $iv ),
			'key missing'  => array( $code, '', $iv ),
			'iv missing'   => array( $code, $key, '' ),
			'all missing'  => array( '', '', '' ),
		);
	}
}
