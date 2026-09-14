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

	/**
	 * @dataProvider availabilityProvider
	 */
	public function test_cloaked_plugins_tab_follows_its_explicit_capability( bool $available ) :void {
		UnitTestControllerFactory::install(
			null,
			null,
			(object)[
				'caps' => new class( $available ) {
					private bool $available;

					public function __construct( bool $available ) {
						$this->available = $available;
					}

					public function canDetectCloakedPlugins() :bool {
						return $this->available;
					}
				},
			]
		);

		$state = ( new ScansResultsRailTabAvailability() )->build( 'hidden_plugins' );

		$this->assertSame( $available, $state[ 'is_available' ] );
		$this->assertSame( $available, $state[ 'show_in_actions_queue' ] );
		$this->assertSame( $available, $state[ 'show_in_fix_now' ] );
		$this->assertSame( $available ? '' : 'not_enabled', $state[ 'disabled_reason' ] );
		$this->assertSame( 'neutral', $state[ 'disabled_status' ] );
		$this->assertSame( [], $state[ 'disabled_actions' ] );
	}

	public static function availabilityProvider() :array {
		return [ 'available' => [ true ], 'unavailable' => [ false ] ];
	}
}
