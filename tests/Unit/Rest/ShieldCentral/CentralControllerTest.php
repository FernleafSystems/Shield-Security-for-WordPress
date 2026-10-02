<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Rest\ShieldCentral;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\CentralController;
use FernleafSystems\Wordpress\Plugin\Shield\Components\ComponentLoader;
use FernleafSystems\Wordpress\Plugin\Shield\Rest\ShieldCentral\Support\{
	CentralDisabledException,
	ConnectionStore,
	PairingSessionStore,
	PairingTokenStore
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginControllerInstaller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\UnitTestControllerFactory;

class CentralControllerTest extends BaseUnitTest {

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
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	/**
	 * @throws \Exception
	 */
	public function test_issue_creates_hash_stored_one_time_token_contract() :void {
		$issued = ( new CentralController() )->issuePairingToken();

		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $issued[ 'pairing_token' ] );
		$this->assertSame( CentralController::PAIRING_TOKEN_TTL, $issued[ 'ttl_seconds' ] );
		$this->assertNotEmpty( $issued[ 'expires_at' ] );
		$this->assertTrue( ( new PairingTokenStore() )->isValid( $issued[ 'pairing_token' ] ) );

		$stored = \get_option( PairingTokenStore::OPTION_KEY, [] );
		$this->assertIsArray( $stored );
		$this->assertArrayHasKey( \hash( 'sha256', $issued[ 'pairing_token' ] ), $stored );
		$this->assertArrayNotHasKey( $issued[ 'pairing_token' ], $stored );
	}

	public function test_default_off_short_circuits_before_plugin_initialization() :void {
		$this->enabled = false;
		PluginControllerInstaller::reset();
		$this->assertFalse( ( new CentralController() )->isEnabled() );
	}

	public function test_component_is_lazy_and_shared() :void {
		$loader = new ComponentLoader();
		$this->assertInstanceOf( CentralController::class, $loader->central );
		$this->assertSame( $loader->central, $loader->central );
	}

	public function test_disabled_operations_preserve_credentials_and_reenable_checks_expiry() :void {
		\Brain\Monkey\Functions\when( 'rest_url' )->justReturn( 'https://shield.test/wp-json/' );
		$central = new CentralController();
		$valid = $central->issuePairingToken();
		$expired = $central->issuePairingToken( -1 );
		$session = ( new PairingSessionStore() )->create( 'session-token' );
		$connection = ( new ConnectionStore() )->create( 'site-uuid' );
		$tokens = \get_option( PairingTokenStore::OPTION_KEY );
		$connections = \get_option( ConnectionStore::OPTION_KEY );
		$this->enabled = false;
		foreach ( [
			fn() => $central->issuePairingToken(),
			fn() => $central->pairBootstrap( $valid[ 'pairing_token' ], 'https://shield.test' ),
			fn() => $central->pairHealth( $session ),
			fn() => $central->pairFinalize( $session, 'site-uuid' ),
			fn() => $central->pairCleanup( $session, 'site-uuid', $connection[ 'connection_material' ] ),
			fn() => $central->siteUnpair( 'site-uuid', $connection[ 'connection_material' ] ),
			fn() => $central->syncCollect( 'site-uuid', $connection[ 'connection_material' ] ),
		] as $operation ) {
			try {
				$operation();
				$this->fail( 'Disabled operation ran.' );
			}
			catch ( CentralDisabledException $e ) {
				$this->assertSame( $tokens, \get_option( PairingTokenStore::OPTION_KEY ) );
				$this->assertSame( $connections, \get_option( ConnectionStore::OPTION_KEY ) );
				$this->assertTrue( ( new PairingSessionStore() )->exists( $session ) );
			}
		}
		$this->enabled = true;
		$this->assertSame( 'invalid_pairing_token', $central->pairBootstrap( $expired[ 'pairing_token' ], '' )[ 'diagnostic_code' ] );
		$this->assertTrue( $central->pairBootstrap( $valid[ 'pairing_token' ], '' )[ 'success' ] );
	}

	public function test_plugin_disabled_and_force_off_cannot_be_overridden_by_opt_in() :void {
		$state = (object)[ 'enabled' => false ];
		$request = (object)[ 'is_force_off' => false ];
		UnitTestControllerFactory::install( null, null, (object)[
			'comps'    => (object)[ 'opts_lookup' => new class( $state ) {
				private object $state;
				public function __construct( object $state ) {
					$this->state = $state;
				}
				public function isPluginEnabled() :bool {
					return $this->state->enabled;
				}
			} ],
			'this_req' => $request,
		] );
		$central = new CentralController();
		$this->assertFalse( $central->isEnabled() );
		$state->enabled = true;
		$request->is_force_off = true;
		$this->assertFalse( $central->isEnabled() );
		$request->is_force_off = false;
		$this->assertTrue( $central->isEnabled() );
	}
}
