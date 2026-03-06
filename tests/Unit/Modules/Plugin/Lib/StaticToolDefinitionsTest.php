<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\Plugin\Lib;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\StaticToolDefinitions;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginControllerInstaller;

class StaticToolDefinitionsTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		$this->installControllerStubs();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_all_definitions_have_unique_ids_and_valid_routes() :void {
		$definitions = StaticToolDefinitions::all();
		$ids = \array_column( $definitions, 'id' );

		$this->assertSame( $ids, \array_values( \array_unique( $ids ) ) );

		foreach ( $definitions as $definition ) {
			$this->assertTrue(
				PluginNavs::NavExists( $definition[ 'nav' ], $definition[ 'subnav' ] ),
				sprintf( 'Invalid route for %s', $definition[ 'id' ] )
			);
		}
	}

	public function test_mode_filters_return_expected_static_tool_ids() :void {
		$this->assertSame(
			[ 'tool_scan_run' ],
			\array_column( StaticToolDefinitions::forMode( PluginNavs::MODE_ACTIONS ), 'id' )
		);

		$this->assertSame(
			[ 'tool_ip_manager', 'tool_activity_log', 'tool_traffic_log', 'tool_sessions' ],
			\array_column( StaticToolDefinitions::forMode( PluginNavs::MODE_INVESTIGATE ), 'id' )
		);

		$this->assertSame(
			[ 'tool_rules_manage', 'tool_rules_build', 'tool_lockdown', 'tool_importexport', 'tool_guidedsetup', 'tool_debug' ],
			\array_column( StaticToolDefinitions::forMode( PluginNavs::MODE_CONFIGURE ), 'id' )
		);
	}

	public function test_search_filter_includes_canonical_reports_and_guided_setup_routes() :void {
		$definitionsById = [];
		foreach ( StaticToolDefinitions::forSearch() as $definition ) {
			$definitionsById[ $definition[ 'id' ] ] = $definition;
		}

		$this->assertSame( PluginNavs::NAV_REPORTS, $definitionsById[ 'tool_reports' ][ 'nav' ] ?? '' );
		$this->assertSame( PluginNavs::SUBNAV_REPORTS_OVERVIEW, $definitionsById[ 'tool_reports' ][ 'subnav' ] ?? '' );
		$this->assertSame( PluginNavs::NAV_WIZARD, $definitionsById[ 'tool_guidedsetup' ][ 'nav' ] ?? '' );
		$this->assertSame( PluginNavs::SUBNAV_WIZARD_WELCOME, $definitionsById[ 'tool_guidedsetup' ][ 'subnav' ] ?? '' );
	}

	private function installControllerStubs() :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->comps = (object)[
			'zones' => new class {
				public function enumZones() :array {
					return [];
				}

				public function enumZoneComponents() :array {
					return [];
				}
			},
		];

		PluginControllerInstaller::install( $controller );
	}
}
