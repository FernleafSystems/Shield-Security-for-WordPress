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

	private object $capture;

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
		$this->capture->queuePayload = $this->buildQueuePayload( false, 'payload-rendered-needs-attention-queue' );
		$page = new PageActionsQueueLanding();
		$content = $this->invokeNonPublicMethod( $page, 'getLandingContent' );

		$meterCalls = \array_values( \array_filter(
			$this->capture->renderCalls,
			static fn( array $call ) :bool => ( $call[ 'action' ] ?? '' ) === MeterCard::class
		) );
		$queueRenderCalls = \array_values( \array_filter(
			$this->capture->renderCalls,
			static fn( array $call ) :bool => ( $call[ 'action' ] ?? '' ) === NeedsAttentionQueue::class
		) );
		$queueActionCalls = \array_values( \array_filter(
			$this->capture->actionCalls,
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

		$this->assertCount( 0, $queueRenderCalls );
		$this->assertCount( 1, $queueActionCalls );
		$this->assertSame( [ 'compact_all_clear' => true ], $queueActionCalls[ 0 ][ 'action_data' ] ?? [] );
		$this->assertSame( 'rendered-meter-card', $content[ 'action_meter' ] ?? '' );
		$this->assertSame( 'payload-rendered-needs-attention-queue', $content[ 'needs_attention_queue' ] ?? '' );
	}

	public function test_landing_flags_reflect_queue_empty_state_from_payload() :void {
		$this->capture->queuePayload = $this->buildQueuePayload( false );
		$pageAllClear = new PageActionsQueueLanding();
		$flagsAllClear = $this->invokeNonPublicMethod( $pageAllClear, 'getLandingFlags' );
		$this->assertTrue( (bool)( $flagsAllClear[ 'queue_is_empty' ] ?? false ) );

		$this->capture->queuePayload = $this->buildQueuePayload( true );
		$pageHasItems = new PageActionsQueueLanding();
		$flagsHasItems = $this->invokeNonPublicMethod( $pageHasItems, 'getLandingFlags' );
		$this->assertFalse( (bool)( $flagsHasItems[ 'queue_is_empty' ] ?? true ) );
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
		$this->capture->queuePayload = $this->buildQueuePayload( false, 'queue-render', 'Last scan: 10 minutes ago' );
		$page = new PageActionsQueueLanding();
		$strings = $this->invokeNonPublicMethod( $page, 'getLandingStrings' );

		$this->assertSame( 'Quick Actions', $strings[ 'cta_title' ] ?? '' );
		$this->assertSame( 'Open Scan Results', $strings[ 'cta_scan_results' ] ?? '' );
		$this->assertSame( 'Run Manual Scan', $strings[ 'cta_scan_run' ] ?? '' );
		$this->assertSame( 'All security zones are clear', $strings[ 'all_clear_title' ] ?? '' );
		$this->assertSame( 'Shield is actively protecting your site. Nothing requires your action.', $strings[ 'all_clear_context' ] ?? '' );
		$this->assertSame( 'Last scan: 10 minutes ago', $strings[ 'all_clear_subtext' ] ?? '' );
		$this->assertSame( 'bi bi-shield-check', $strings[ 'all_clear_icon_class' ] ?? '' );
	}

	public function test_render_data_fetches_queue_payload_once_for_content_flags_and_strings() :void {
		$this->capture->queuePayload = $this->buildQueuePayload( false, 'rendered-queue', 'Last scan: 3 minutes ago' );
		$page = new PageActionsQueueLanding();
		$renderData = $this->invokeNonPublicMethod( $page, 'getRenderData' );
		$queueActionCalls = \array_values( \array_filter(
			$this->capture->actionCalls,
			static fn( array $call ) :bool => ( $call[ 'action' ] ?? '' ) === NeedsAttentionQueue::class
		) );

		$this->assertCount( 1, $queueActionCalls );
		$this->assertSame( [ 'compact_all_clear' => true ], $queueActionCalls[ 0 ][ 'action_data' ] ?? [] );
		$this->assertSame( 'rendered-queue', $renderData[ 'content' ][ 'needs_attention_queue' ] ?? '' );
		$this->assertTrue( (bool)( $renderData[ 'flags' ][ 'queue_is_empty' ] ?? false ) );
		$this->assertSame( 'Last scan: 3 minutes ago', $renderData[ 'strings' ][ 'all_clear_subtext' ] ?? '' );
	}

	private function installControllerStub() :void {
		$this->capture = (object)[
			'renderCalls' => [],
			'actionCalls' => [],
			'queuePayload' => $this->buildQueuePayload( false ),
		];

		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->plugin_urls = new class {
			public function adminTopNav( string $nav, string $subnav = '' ) :string {
				return '/admin/'.$nav.'/'.$subnav;
			}
		};
		$controller->action_router = new class( $this->capture ) {
			private object $capture;

			public function __construct( object $capture ) {
				$this->capture = $capture;
			}

			public function render( string $action, array $actionData = [] ) :string {
				$this->capture->renderCalls[] = [
					'action'      => $action,
					'action_data' => $actionData,
				];

				if ( $action === MeterCard::class ) {
					return 'rendered-meter-card';
				}
				return 'rendered-unknown';
			}

			public function action( string $action, array $actionData = [] ) {
				$this->capture->actionCalls[] = [
					'action'      => $action,
					'action_data' => $actionData,
				];

				return new class( $this->capture ) {
					private object $capture;

					public function __construct( object $capture ) {
						$this->capture = $capture;
					}

					public function payload() :array {
						return $this->capture->queuePayload;
					}
				};
			}
		};

		PluginControllerInstaller::install( $controller );
	}

	private function buildQueuePayload( bool $hasItems, string $renderOutput = 'rendered-needs-attention-queue', string $subtext = '' ) :array {
		return [
			'render_output' => $renderOutput,
			'render_data'   => [
				'flags'   => [
					'has_items' => $hasItems,
				],
				'strings' => [
					'all_clear_title'      => 'All security zones are clear',
					'all_clear_subtitle'   => 'Shield is actively protecting your site. Nothing requires your action.',
					'status_strip_subtext' => $subtext,
					'all_clear_icon_class' => 'bi bi-shield-check',
				],
			],
		];
	}
}
