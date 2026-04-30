<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\SecurityAdmin\Lib\SecurityAdmin;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\SecurityAdminRemove;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\SecurityAdmin\SecurityAdminDisableActionBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	UnitTestControllerFactory,
	UnitTestPluginUrls
};

class SecurityAdminDisableActionBuilderTest extends BaseUnitTest {

	private object $secAdminController;

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		$this->secAdminController = new class {
			public bool $enabled = true;

			public function isEnabledSecAdmin() :bool {
				return $this->enabled;
			}
		};
		UnitTestControllerFactory::install(
			new UnitTestPluginUrls(),
			null,
			new class( $this->secAdminController ) {
				public object $comps;

				public function __construct( object $secAdminController ) {
					$this->comps = (object)[
						'sec_admin' => $secAdminController,
					];
				}
			}
		);
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_build_zone_and_context_actions_use_remove_action_without_auxiliary_flags() :void {
		$builder = new SecurityAdminDisableActionBuilder();

		$zoneAction = $builder->buildZoneAction();
		$contextHref = $builder->buildContextualHref();
		$configureActions = $builder->buildConfigureContextActions();

		$this->assertIsArray( $zoneAction );
		$this->assertIsArray( $contextHref );
		$this->assertCount( 1, $configureActions );
		$this->assertSame( 'href', $configureActions[ 0 ][ 'kind' ] ?? '' );
		$this->assertSame( 'deactivate', $configureActions[ 0 ][ 'type' ] ?? '' );
		$this->assertSecurityAdminRemoveHref( (string)( $zoneAction[ 'href' ] ?? '' ) );
		$this->assertSecurityAdminRemoveHref( (string)( $contextHref[ 'href' ] ?? '' ) );
		$this->assertSecurityAdminRemoveHref( (string)( $configureActions[ 0 ][ 'href' ] ?? '' ) );
	}

	public function test_build_returns_empty_when_security_admin_is_disabled() :void {
		$this->secAdminController->enabled = false;
		$builder = new SecurityAdminDisableActionBuilder();

		$this->assertNull( $builder->buildZoneAction() );
		$this->assertNull( $builder->buildContextualHref() );
		$this->assertSame( [], $builder->buildConfigureContextActions() );
	}

	private function assertSecurityAdminRemoveHref( string $href ) :void {
		$query = \parse_url( $href, \PHP_URL_QUERY );
		$this->assertIsString( $query );
		$params = [];
		\parse_str( $query, $params );

		$this->assertSame( 'shield_action', $params[ 'action' ] ?? '' );
		$this->assertSame( SecurityAdminRemove::SLUG, $params[ 'ex' ] ?? '' );
		$this->assertNotEmpty( $params[ 'exnonce' ] ?? '' );
		$this->assertArrayNotHasKey( 'quietly', $params );
	}
}
