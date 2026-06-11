<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Rest\ShieldCentral;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Rest\ShieldCentral\Support\ConnectionStore;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class ConnectionStoreTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( 'rest_url' )->alias(
			static fn( string $path = '' ) :string => 'https://shield.test/wp-json/'.\ltrim( $path, '/' )
		);
	}

	public function test_create_returns_raw_secret_once_and_stores_verifiable_hash() :void {
		$store = new ConnectionStore();
		$connection = $store->create( 'site-uuid' );

		$this->assertSame( 'hmac', $connection[ 'preferred_auth_mode' ] );
		$this->assertSame( [ 'hmac' ], $connection[ 'established_auth_modes' ] );
		$this->assertSame( 'https://shield.test/wp-json/shield/v1/shield-central', $connection[ 'trusted_endpoint_url' ] );
		$this->assertNotEmpty( $connection[ 'connection_material' ][ 'shared_secret' ] );

		$stored = $store->read();
		$this->assertSame( 'site-uuid', $stored[ 'site_uuid' ] );
		$this->assertArrayHasKey( 'shared_secret_hash', $stored );
		$this->assertArrayNotHasKey( 'shared_secret', $stored );

		$this->assertTrue( $store->verify( 'site-uuid', $connection[ 'connection_material' ] ) );
		$this->assertFalse( $store->verify( 'site-uuid', [ 'shared_secret' => 'wrong' ] ) );
	}

	public function test_delete_requires_valid_connection_material() :void {
		$store = new ConnectionStore();
		$connection = $store->create( 'site-uuid' );

		$this->assertFalse( $store->delete( 'site-uuid', [ 'shared_secret' => 'wrong' ] ) );
		$this->assertNotEmpty( $store->read() );

		$this->assertTrue( $store->delete( 'site-uuid', $connection[ 'connection_material' ] ) );
		$this->assertSame( [], $store->read() );
	}
}
