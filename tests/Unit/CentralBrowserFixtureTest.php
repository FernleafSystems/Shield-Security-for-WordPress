<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use Brain\Monkey\Filters;
use FernleafSystems\Wordpress\Plugin\Shield\Components\ComponentLoader;
use FernleafSystems\Wordpress\Plugin\Shield\Rest\ShieldCentral\Support\ConnectionStore;
use FernleafSystems\Wordpress\Plugin\Shield\Rest\ShieldCentral\Support\PairingTokenStore;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter\CentralFixtureBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\BrowserFixtureRegistry;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginControllerInstaller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\UnitTestControllerFactory;

class CentralBrowserFixtureTest extends BaseUnitTest {

	private $originalWpdb;

	protected function setUp() :void {
		parent::setUp();
		$this->originalWpdb = $GLOBALS[ 'wpdb' ] ?? null;
		$GLOBALS[ 'wpdb' ] = new class {
			public string $options = 'wp_options';
			public function esc_like( string $value ) :string {
				return \addcslashes( $value, '_%\\' );
			}
			public function prepare( string $sql, string $pattern ) :string {
				return $sql;
			}
			public function get_col( string $sql ) :array {
				return [ '_site_transient_shield_central_pairing_session_fixture' ];
			}
		};
	}

	protected function tearDown() :void {
		$GLOBALS[ 'wpdb' ] = $this->originalWpdb;
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function testResetClearsOnlyCentralStateAndCleanupDisablesOptIn() :void {
		\update_option( ConnectionStore::OPTION_KEY, [ 'site_uuid' => 'site', 'shared_secret_hash' => 'private' ] );
		\update_option( PairingTokenStore::OPTION_KEY, [ 'old' => [] ] );
		\update_option( 'unrelated', 'retained' );
		\set_site_transient( 'shield_central_pairing_session_fixture', [ 'old' => true ], 600 );
		$this->assertSame( [ 'connected' => true, 'site_uuid' => 'site' ], BrowserFixtureRegistry::run( 'central', 'inspect' ) );
		foreach ( [ 'reset', 'reset', 'cleanup' ] as $action ) {
			$this->assertSame( [ 'connected' => false, 'site_uuid' => null ], BrowserFixtureRegistry::run( 'central', $action ) );
			$this->assertSame( $action === 'reset', (bool)\get_option( CentralFixtureBuilder::ENABLED_OPTION ) );
		}
		$this->assertFalse( \get_site_transient( 'shield_central_pairing_session_fixture' ) );
		$this->assertFalse( \get_option( PairingTokenStore::OPTION_KEY ) );
		$this->assertSame( 'retained', \get_option( 'unrelated' ) );
	}

	public function testRevocationRetainsTokenAndUsesRealTokenIssuance() :void {
		Filters\expectApplied( 'shield/central/enabled' )->andReturn( true );
		UnitTestControllerFactory::install( null, null, (object)[
			'comps' => new ComponentLoader(),
			'opts' => new class {
				public function optIs( string $key, string $value ) :bool {
					return true;
				}
			},
			'this_req' => (object)[ 'is_force_off' => false ],
		] );
		$issued = BrowserFixtureRegistry::run( 'central', 'issue-token' );
		$this->assertTrue( ( new PairingTokenStore() )->isValid( $issued[ 'pairing_token' ] ) );
		\update_option( ConnectionStore::OPTION_KEY, [ 'site_uuid' => 'site' ] );
		$this->assertFalse( BrowserFixtureRegistry::run( 'central', 'revoke-connection' )[ 'connected' ] );
		$this->assertTrue( ( new PairingTokenStore() )->isValid( $issued[ 'pairing_token' ] ) );
	}

	public function testUnknownOperationIsRejected() :void {
		$this->expectException( \InvalidArgumentException::class );
		BrowserFixtureRegistry::run( 'central', 'replace-connection' );
	}
}
