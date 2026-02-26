<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Meters\ProgressMeters;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\{
	Handler,
	Meter\MeterOverallConfig,
	Meter\MeterSummary
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\InvokesNonPublicMethods;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginControllerInstaller;

class ProgressMetersBehaviorTest extends BaseUnitTest {

	use InvokesNonPublicMethods;

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		$this->installControllerStub();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_valid_mixed_case_channel_is_normalized() :void {
		$renderData = $this->invokeNonPublicMethod( new ProgressMeters( [
			'meter_channel' => '  ConFig ',
		] ), 'getRenderData' );

		$this->assertSame( 'config', (string)( $renderData[ 'vars' ][ 'meter_channel' ] ?? '' ) );
	}

	public function test_invalid_channel_becomes_empty_string_in_render_vars() :void {
		$renderData = $this->invokeNonPublicMethod( new ProgressMeters( [
			'meter_channel' => 'invalid-channel',
		] ), 'getRenderData' );

		$this->assertSame( '', (string)( $renderData[ 'vars' ][ 'meter_channel' ] ?? 'not-empty' ) );
	}

	public function test_meter_slug_contract_remains_unchanged() :void {
		$renderData = $this->invokeNonPublicMethod( new ProgressMeters(), 'getRenderData' );

		$this->assertSame( MeterSummary::SLUG, (string)( $renderData[ 'vars' ][ 'primary_meter_slug' ] ?? '' ) );
		$this->assertSame(
			\array_diff( \array_keys( Handler::METERS ), [ MeterSummary::SLUG, MeterOverallConfig::SLUG ] ),
			$renderData[ 'vars' ][ 'meter_slugs' ] ?? []
		);
	}

	private function installControllerStub() :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->svgs = new class {
			public function iconClass( string $icon ) :string {
				return 'bi bi-'.$icon;
			}
		};

		PluginControllerInstaller::install( $controller );
	}

}
