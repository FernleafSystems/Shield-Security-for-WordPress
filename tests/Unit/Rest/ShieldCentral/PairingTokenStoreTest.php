<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Rest\ShieldCentral;

use FernleafSystems\Wordpress\Plugin\Shield\Rest\ShieldCentral\Support\PairingTokenStore;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class PairingTokenStoreTest extends BaseUnitTest {

	public function test_token_is_valid_until_it_expires() :void {
		$store = new PairingTokenStore();
		$store->storeToken( 'pair-token', 60 );

		$this->assertTrue( $store->isValid( 'pair-token' ) );
		$this->assertFalse( $store->isValid( 'other-token' ) );
	}

	public function test_expired_token_is_pruned_and_rejected() :void {
		$store = new PairingTokenStore();
		$store->storeToken( 'pair-token', -1 );

		$this->assertFalse( $store->isValid( 'pair-token' ) );
	}

	public function test_forget_removes_a_valid_token() :void {
		$store = new PairingTokenStore();
		$store->storeToken( 'pair-token', 60 );

		$store->forget( 'pair-token' );

		$this->assertFalse( $store->isValid( 'pair-token' ) );
	}
}
