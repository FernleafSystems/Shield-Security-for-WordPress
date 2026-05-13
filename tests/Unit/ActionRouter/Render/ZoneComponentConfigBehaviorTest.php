<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\OffCanvas\ZoneComponentConfig;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Options\OptionsFormFor;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	InvokesNonPublicMethods,
	PluginControllerInstaller
};
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component\Modules\{
	ModuleScans,
	ModuleSpam
};

class ZoneComponentConfigBehaviorTest extends BaseUnitTest {

	use InvokesNonPublicMethods;

	private object $renderCapture;

	protected function setUp() :void {
		parent::setUp();
		$this->installControllerStub();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_build_canvas_body_passes_config_item_through_to_options_form() :void {
		$action = new ZoneComponentConfig( [
			'zone_component_slug' => ModuleScans::Slug().','.ModuleSpam::Slug(),
			'config_item'         => 'scan_frequency',
			'form_context'        => 'expansion',
		] );

		$body = $this->invokeNonPublicMethod( $action, 'buildCanvasBody' );

		$this->assertSame( 'rendered-1', $body );
		$this->assertSame( OptionsFormFor::class, $this->renderCapture->calls[ 0 ][ 'action' ] ?? '' );
		$this->assertSame(
			[
				'scan_frequency',
				'file_scan_areas',
				'trusted_commenter_minimum',
			],
			$this->renderCapture->calls[ 0 ][ 'action_data' ][ 'options' ] ?? []
		);
		$this->assertSame( 'scan_frequency', $this->renderCapture->calls[ 0 ][ 'action_data' ][ 'config_item' ] ?? '' );
		$this->assertSame( 'expansion', $this->renderCapture->calls[ 0 ][ 'action_data' ][ 'form_context' ] ?? '' );
		$this->assertArrayNotHasKey( 'focus_option', $this->renderCapture->calls[ 0 ][ 'action_data' ] ?? [] );
	}

	public function test_build_canvas_body_filters_options_when_option_keys_are_provided() :void {
		$action = new ZoneComponentConfig( [
			'zone_component_slug' => ModuleScans::Slug(),
			'option_keys'         => 'file_scan_areas',
		] );

		$body = $this->invokeNonPublicMethod( $action, 'buildCanvasBody' );

		$this->assertSame( 'rendered-1', $body );
		$this->assertSame(
			[
				'file_scan_areas',
			],
			$this->renderCapture->calls[ 0 ][ 'action_data' ][ 'options' ] ?? []
		);
		$this->assertSame( '', $this->renderCapture->calls[ 0 ][ 'action_data' ][ 'config_item' ] ?? 'unexpected' );
		$this->assertSame( 'offcanvas', $this->renderCapture->calls[ 0 ][ 'action_data' ][ 'form_context' ] ?? '' );
	}

	private function installControllerStub() :void {
		$this->renderCapture = (object)[
			'calls' => [],
		];

		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->action_router = new class( $this->renderCapture ) {
			private object $capture;

			public function __construct( object $capture ) {
				$this->capture = $capture;
			}

			public function render( string $action, array $actionData = [] ) :string {
				$this->capture->calls[] = [
					'action'      => $action,
					'action_data' => $actionData,
				];
				return 'rendered-'.\count( $this->capture->calls );
			}
		};
		$controller->comps = (object)[
			'zones' => new class {
				public function getZoneComponent( string $slug ) :object {
					switch ( $slug ) {
						case ModuleScans::Slug():
							return new class {
								public function getOptions() :array {
									return [
										'scan_frequency',
										'file_scan_areas',
									];
								}
							};
						case ModuleSpam::Slug():
							return new class {
								public function getOptions() :array {
									return [
										'trusted_commenter_minimum',
									];
								}
							};
						default:
							return new class {
								public function getOptions() :array {
									return [];
								}
							};
					}
				}
			},
		];

		PluginControllerInstaller::install( $controller );
	}
}
