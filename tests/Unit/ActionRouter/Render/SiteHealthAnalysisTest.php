<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\SiteHealth\Analysis;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	UnitTestControllerFactory,
	UnitTestPluginUrls
};

class SiteHealthAnalysisTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		UnitTestControllerFactory::install(
			new UnitTestPluginUrls(),
			null,
			(object)[
				'labels' => (object)[
					'Name' => 'Shield',
				],
			]
		);
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_render_data_classifies_zone_signals_without_grade_dependencies() :void {
		$page = new class extends Analysis {
			public function exposeRenderData() :array {
				return $this->getRenderData();
			}

			protected function buildZoneSignals() :array {
				return [
					[
						'id'           => 'protected-one',
						'is_protected' => true,
						'weight'       => 7,
					],
					[
						'id'           => 'critical-one',
						'is_protected' => false,
						'weight'       => self::CRITICAL_BOUNDARY,
					],
					[
						'id'           => 'critical-two',
						'is_protected' => false,
						'weight'       => 6,
					],
					[
						'id'           => 'improvement-one',
						'is_protected' => false,
						'weight'       => self::CRITICAL_BOUNDARY - 1,
					],
				];
			}
		};

		$renderData = $page->exposeRenderData();

		$this->assertSame( '/admin/home', $renderData[ 'hrefs' ][ 'dashboard_home' ] ?? '' );
		$this->assertSame( [ 'protected-one' ], \array_column( $renderData[ 'vars' ][ 'protected_components' ] ?? [], 'id' ) );
		$this->assertSame(
			[ 'critical-one', 'critical-two' ],
			\array_column( $renderData[ 'vars' ][ 'critical_components' ] ?? [], 'id' )
		);
		$this->assertSame(
			[ 'improvement-one' ],
			\array_column( $renderData[ 'vars' ][ 'improvement_components' ] ?? [], 'id' )
		);
		$this->assertArrayNotHasKey( 'posture', $renderData[ 'vars' ] ?? [] );
		$this->assertArrayNotHasKey( 'grades', $renderData[ 'vars' ] ?? [] );
	}
}
