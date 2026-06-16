<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ScansResultsRailTabAvailability;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	UnitTestControllerFactory
};

class ScansResultsRailTabAvailabilityTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_hidden_plugins_tab_is_available_through_its_explicit_capability() :void {
		UnitTestControllerFactory::install(
			null,
			null,
			(object)[
				'caps' => new class {
					public function canDetectHiddenPlugins() :bool {
						return true;
					}
				},
			]
		);

		$state = ( new ScansResultsRailTabAvailability() )->build( 'hidden_plugins' );

		$this->assertTrue( $state[ 'is_available' ] );
		$this->assertTrue( $state[ 'show_in_actions_queue' ] );
		$this->assertTrue( $state[ 'show_in_fix_now' ] );
		$this->assertSame( '', $state[ 'disabled_reason' ] );
		$this->assertSame( [], $state[ 'disabled_actions' ] );
	}
}
