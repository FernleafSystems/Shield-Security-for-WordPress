<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Rest\ShieldCentral;

use FernleafSystems\Wordpress\Plugin\Shield\Rest\ShieldCentral\Support\{
	PairingTokenIssuer,
	PairingTokenStore
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class PairingTokenIssuerTest extends BaseUnitTest {

	/**
	 * @throws \Exception
	 */
	public function test_issue_creates_hash_stored_one_time_token_contract() :void {
		$issued = ( new PairingTokenIssuer() )->issue();

		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $issued[ 'pairing_token' ] );
		$this->assertSame( PairingTokenIssuer::TTL_SECONDS, $issued[ 'ttl_seconds' ] );
		$this->assertNotEmpty( $issued[ 'expires_at' ] );
		$this->assertTrue( ( new PairingTokenStore() )->isValid( $issued[ 'pairing_token' ] ) );

		$stored = \get_option( PairingTokenStore::OPTION_KEY, [] );
		$this->assertIsArray( $stored );
		$this->assertArrayHasKey( \hash( 'sha256', $issued[ 'pairing_token' ] ), $stored );
		$this->assertArrayNotHasKey( $issued[ 'pairing_token' ], $stored );
	}
}
