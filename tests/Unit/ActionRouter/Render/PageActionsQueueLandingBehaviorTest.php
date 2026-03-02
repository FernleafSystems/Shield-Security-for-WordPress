<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Meters\MeterCard;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\NeedsAttentionQueue;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PageActionsQueueLanding;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\{
	Component\Base as MeterComponent,
	Meter\MeterSummary
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	InvokesNonPublicMethods,
	PluginControllerInstaller
};

class PageActionsQueueLandingBehaviorTest extends BaseUnitTest {

	use InvokesNonPublicMethods;

	private object $renderCapture;

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		$this->installControllerStub();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_landing_content_includes_action_meter_and_needs_attention_queue() :void {
		$page = new PageActionsQueueLanding();
		$content = $this->invokeNonPublicMethod( $page, 'getLandingContent' );

		$meterCalls = \array_values( \array_filter(
			$this->renderCapture->calls,
			static fn( array $call ) :bool => ( $call[ 'action' ] ?? '' ) === MeterCard::class
		) );
		$queueCalls = \array_values( \array_filter(
			$this->renderCapture->calls,
			static fn( array $call ) :bool => ( $call[ 'action' ] ?? '' ) === NeedsAttentionQueue::class
		) );

		$this->assertCount( 1, $meterCalls );
		$this->assertSame(
			[
				'meter_slug'    => MeterSummary::SLUG,
				'meter_channel' => MeterComponent::CHANNEL_ACTION,
				'is_hero'       => true,
			],
			$meterCalls[ 0 ][ 'action_data' ] ?? []
		);

		$this->assertCount( 1, $queueCalls );
		$this->assertSame( [], $queueCalls[ 0 ][ 'action_data' ] ?? [] );

		$this->assertSame( 'rendered-meter-card', $content[ 'action_meter' ] ?? '' );
		$this->assertSame( 'rendered-needs-attention-queue', $content[ 'needs_attention_queue' ] ?? '' );
	}

	public function test_landing_hrefs_include_scan_results_and_run_routes() :void {
		$page = new PageActionsQueueLanding();
		$hrefs = $this->invokeNonPublicMethod( $page, 'getLandingHrefs' );

		$this->assertSame(
			[
				'scan_results' => '/admin/scans/results',
				'scan_run'     => '/admin/scans/run',
			],
			$hrefs
		);
	}

	public function test_landing_strings_preserve_quick_actions_copy() :void {
		$page = new PageActionsQueueLanding();
		$strings = $this->invokeNonPublicMethod( $page, 'getLandingStrings' );

		$this->assertSame( 'Quick Actions', $strings[ 'cta_title' ] ?? '' );
		$this->assertSame( 'Open Scan Results', $strings[ 'cta_scan_results' ] ?? '' );
		$this->assertSame( 'Run Manual Scan', $strings[ 'cta_scan_run' ] ?? '' );
	}

	private function installControllerStub() :void {
		$this->renderCapture = (object)[
			'calls' => [],
		];

		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->plugin_urls = new class {
			public function adminTopNav( string $nav, string $subnav = '' ) :string {
				return '/admin/'.$nav.'/'.$subnav;
			}
		};
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

				if ( $action === MeterCard::class ) {
					return 'rendered-meter-card';
				}
				if ( $action === NeedsAttentionQueue::class ) {
					return 'rendered-needs-attention-queue';
				}
				return 'rendered-unknown';
			}
		};

		PluginControllerInstaller::install( $controller );
	}
}
