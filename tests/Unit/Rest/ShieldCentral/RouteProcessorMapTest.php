<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Rest\ShieldCentral;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\CentralController;
use FernleafSystems\Wordpress\Plugin\Shield\Components\ComponentLoader;
use FernleafSystems\Wordpress\Plugin\Shield\Rest\ShieldCentral\Support\{
	ConnectionStore,
	PairingTokenStore
};
use FernleafSystems\Wordpress\Plugin\Shield\Rest\ShieldCentral\v1\Route\{
	PairBootstrap,
	PairCleanup,
	PairFinalize,
	RouteProcessorMap,
	SiteUnpair,
	SyncCollect
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginControllerInstaller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\UnitTestControllerFactory;

class RouteProcessorMapTest extends BaseUnitTest {

	private bool $enabled = true;

	protected function setUp() :void {
		parent::setUp();
		\Brain\Monkey\Filters\expectApplied( 'shield/central/enabled' )->andReturnUsing( fn() => $this->enabled );
		UnitTestControllerFactory::install( null, null, (object)[
			'comps'    => new ComponentLoader(),
			'opts'     => new class {
				public function optIs( string $key, string $value ) :bool {
					return true;
				}
			},
			'this_req' => (object)[ 'is_force_off' => false ],
		] );
		Functions\when( 'rest_url' )->alias(
			static fn( string $path = '' ) :string => 'https://shield.test/wp-json/'.\ltrim( $path, '/' )
		);
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_retained_processors_reject_after_disabling() :void {
		$map = ( new RouteProcessorMap() )->map();
		$this->enabled = false;
		foreach ( $map as $processor ) {
			$result = $processor( new \WP_REST_Request( [] ) );
			$this->assertFalse( $result[ 'success' ] );
			$this->assertSame( 'central_disabled', $result[ 'diagnostic_code' ] );
		}
	}

	public function test_pairing_flow_creates_connection_material_and_unpairs_with_valid_secret() :void {
		$issued = ( new CentralController() )->issuePairingToken();
		$map = ( new RouteProcessorMap() )->map();

		$bootstrap = $map[ PairBootstrap::class ]( new \WP_REST_Request( [
			'pairing_token' => $issued[ 'pairing_token' ],
			'site_url'      => 'https://shield.test',
		] ) );

		$this->assertTrue( $bootstrap[ 'success' ] );
		$this->assertNotEmpty( $bootstrap[ 'pairing_session' ] );
		$this->assertFalse( ( new PairingTokenStore() )->isValid( $issued[ 'pairing_token' ] ) );

		$reuse = $map[ PairBootstrap::class ]( new \WP_REST_Request( [
			'pairing_token' => $issued[ 'pairing_token' ],
			'site_url'      => 'https://shield.test',
		] ) );

		$this->assertFalse( $reuse[ 'success' ] );
		$this->assertSame( 'invalid_pairing_token', $reuse[ 'diagnostic_code' ] );

		$finalize = $map[ PairFinalize::class ]( new \WP_REST_Request( [
			'pairing_session' => $bootstrap[ 'pairing_session' ],
			'site_uuid'       => 'site-uuid',
		] ) );

		$this->assertTrue( $finalize[ 'success' ] );
		$this->assertSame( 'hmac', $finalize[ 'preferred_auth_mode' ] );
		$this->assertNotEmpty( $finalize[ 'connection_material' ][ 'shared_secret' ] );

		$unpair = $map[ SiteUnpair::class ]( new \WP_REST_Request( [
			'site_uuid'           => 'site-uuid',
			'connection_material' => $finalize[ 'connection_material' ],
		] ) );

		$this->assertTrue( $unpair[ 'success' ] );
	}

	public function test_pair_cleanup_removes_finalized_connection_with_valid_secret() :void {
		$issued = ( new CentralController() )->issuePairingToken();
		$map = ( new RouteProcessorMap() )->map();

		$bootstrap = $map[ PairBootstrap::class ]( new \WP_REST_Request( [
			'pairing_token' => $issued[ 'pairing_token' ],
			'site_url'      => 'https://shield.test',
		] ) );

		$finalize = $map[ PairFinalize::class ]( new \WP_REST_Request( [
			'pairing_session' => $bootstrap[ 'pairing_session' ],
			'site_uuid'       => 'site-uuid',
		] ) );

		$this->assertTrue( ( new ConnectionStore() )->verify( 'site-uuid', $finalize[ 'connection_material' ] ) );

		$cleanup = $map[ PairCleanup::class ]( new \WP_REST_Request( [
			'site_uuid'           => 'site-uuid',
			'connection_material' => $finalize[ 'connection_material' ],
		] ) );

		$this->assertTrue( $cleanup[ 'success' ] );
		$this->assertSame( [], ( new ConnectionStore() )->read() );
	}

	public function test_pair_cleanup_keeps_connection_when_material_is_invalid() :void {
		$store = new ConnectionStore();
		$connection = $store->create( 'site-uuid' );

		$response = ( ( new RouteProcessorMap() )->map()[ PairCleanup::class ] )( new \WP_REST_Request( [
			'site_uuid'           => 'site-uuid',
			'connection_material' => [ 'shared_secret' => 'wrong' ],
		] ) );

		$this->assertFalse( $response[ 'success' ] );
		$this->assertTrue( $response[ 'reconnect_required' ] );
		$this->assertSame( 'invalid_connection_material', $response[ 'diagnostic_code' ] );
		$this->assertTrue( $store->verify( 'site-uuid', $connection[ 'connection_material' ] ) );
	}

	public function test_invalid_pairing_token_returns_app_level_failure() :void {
		$response = ( ( new RouteProcessorMap() )->map()[ PairBootstrap::class ] )( new \WP_REST_Request( [
			'pairing_token' => 'bad-token',
		] ) );

		$this->assertFalse( $response[ 'success' ] );
		$this->assertSame( 'invalid_pairing_token', $response[ 'diagnostic_code' ] );
	}

	public function test_invalid_sync_secret_requests_reconnect_without_throwing() :void {
		$response = ( ( new RouteProcessorMap() )->map()[ SyncCollect::class ] )( new \WP_REST_Request( [
			'site_uuid'           => 'site-uuid',
			'connection_material' => [ 'shared_secret' => 'wrong' ],
		] ) );

		$this->assertFalse( $response[ 'success' ] );
		$this->assertTrue( $response[ 'reconnect_required' ] );
		$this->assertSame( 'invalid_connection_material', $response[ 'diagnostic_code' ] );
	}
}
