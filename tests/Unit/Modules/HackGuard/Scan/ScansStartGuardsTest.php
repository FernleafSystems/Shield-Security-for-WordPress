<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Scan;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\ScanProgressBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\ScansStart;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\ScansController;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\StartScansResult;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestRequest
};

class ScansStartGuardsTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_cli_is_exempt_from_loopback_guard_but_web_is_not() :void {
		$this->installController( false );

		$scans = new ScansController();

		$this->assertNotSame( '', $scans->getStartBlockedMessage() );
		$this->assertSame( '', $scans->getStartBlockedMessage( true ) );
	}

	public function test_blocked_start_reports_why_scans_cannot_run() :void {
		$this->installActionController( false, StartScansResult::fromRequested( [] ) );

		[ $payload, $progress ] = $this->runStart( [] );

		$this->assertFalse( $payload[ 'success' ] );
		$this->assertSame( [ [ 'forFailure', 'blocked' ] ], $progress->calls );
		$this->assertSame( ScansStartRecordingProgress::FAILED, $this->withoutSuccess( $payload ) );
	}

	public function test_selected_scan_that_cannot_start_is_reported_as_failed_with_the_reason() :void {
		$result = StartScansResult::fromRequested( [ 'afs' ] )->addFailure( 'afs', StartScansResult::REASON_CREATE_FAILED );
		$controller = $this->installActionController( true, $result );

		[ $payload, $progress ] = $this->runStart( [ 'afs' => 'Y' ] );

		$this->assertFalse( $payload[ 'success' ] );
		$this->assertNotSame( '', $result->getMessage() );
		$this->assertSame( [ [ 'forFailure', $result->getMessage() ] ], $progress->calls );
		$this->assertSame( [ [ 'afs' ] ], $controller->comps->scans->startCalls );
		$this->assertSame( [ false ], $controller->comps->scans->resetIgnoreCalls );
	}

	public function test_active_duplicate_is_followed_as_a_resumed_scan() :void {
		$this->installActionController( true, StartScansResult::fromRequested( [ 'afs' ] )->addResumed( 'afs', 44 ) );

		[ $payload, $progress ] = $this->runStart( [ 'afs' => 'Y' ] );

		$this->assertTrue( $payload[ 'success' ] );
		$this->assertSame( [ [ 'forScans', [ 44 ] ] ], $progress->calls );
		$this->assertSame( ScansStartRecordingProgress::RUNNING + [ 'scan_ids' => [ 44 ] ], $this->withoutSuccess( $payload ) );
	}

	public function test_partial_start_follows_the_scans_that_did_start() :void {
		$controller = $this->installActionController(
			true,
			StartScansResult::fromRequested( [ 'afs', 'wpv' ] )
							->addStarted( 'afs', 31 )
							->addFailure( 'wpv', StartScansResult::REASON_CREATE_FAILED )
		);

		[ $payload, $progress ] = $this->runStart( [ 'afs' => 'Y', 'wpv' => 'Y' ] );

		$this->assertTrue( $payload[ 'success' ] );
		$this->assertSame( [ [ 'forScans', [ 31 ] ] ], $progress->calls );
		$this->assertSame( [ 31 ], $payload[ 'scan_ids' ] );
		$this->assertSame( [ [ 'afs', 'wpv' ] ], $controller->comps->scans->startCalls );
	}

	public function test_only_known_scans_are_started() :void {
		$controller = $this->installActionController( true, StartScansResult::fromRequested( [ 'afs' ] )->addStarted( 'afs', 31 ) );

		$this->runStart( [ 'afs' => 'Y', 'not_a_scan' => 'Y' ] );

		$this->assertSame( [ [ 'afs' ] ], $controller->comps->scans->startCalls );
	}

	public function test_start_passes_reset_ignore_flag_to_central_start() :void {
		$controller = $this->installActionController( true, StartScansResult::fromRequested( [ 'afs' ] )->addStarted( 'afs', 31 ) );

		$this->runStart( [ 'afs' => 'Y', 'opt_clear_ignore' => 'Y' ] );

		$this->assertSame( [ [ 'afs' ] ], $controller->comps->scans->startCalls );
		$this->assertSame( [ true ], $controller->comps->scans->resetIgnoreCalls );
	}

	/**
	 * @return array{0:array<string,mixed>,1:ScansStartRecordingProgress}
	 */
	private function runStart( array $post ) :array {
		$request = new UnitTestRequest();
		$request->post = $post;
		ServicesState::installItems( [
			'service_request' => $request,
		] );

		$action = new ScansStartUnderTest( ExternalEntryGuard::ActionDataFromTransport( $request->post ) );
		$action->recordingProgress = new ScansStartRecordingProgress();
		$method = new \ReflectionMethod( ScansStart::class, 'exec' );
		$method->setAccessible( true );
		$method->invoke( $action );

		return [ $action->response()->payload(), $action->recordingProgress ];
	}

	private function withoutSuccess( array $payload ) :array {
		unset( $payload[ 'success' ] );
		return $payload;
	}

	private function installController( bool $canLoopback ) :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->plugin = new class( $canLoopback ) extends ModCon {
			private bool $canLoopback;

			public function __construct( bool $canLoopback ) {
				$this->canLoopback = $canLoopback;
			}

			public function canSiteLoopback() :bool {
				return $this->canLoopback;
			}
		};

		PluginControllerInstaller::install( $controller );
	}

	private function installActionController( bool $canStart, StartScansResult $startResult ) :Controller {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->comps = (object)[
			'scans' => new class( $canStart, $startResult ) {
				public array $startCalls = [];
				public array $resetIgnoreCalls = [];

				private bool $canStart;
				private StartScansResult $startResult;

				public function __construct( bool $canStart, StartScansResult $startResult ) {
					$this->canStart = $canStart;
					$this->startResult = $startResult;
				}

				public function getStartBlockedMessage( bool $isCli = false ) :string {
					unset( $isCli );
					return $this->canStart ? '' : 'blocked';
				}

				public function canStartScans( bool $isCli = false ) :bool {
					unset( $isCli );
					return $this->canStart;
				}

				public function getScanSlugs() :array {
					return [ 'afs', 'apc', 'wpv' ];
				}

				public function startNewScans( array $scans, bool $resetIgnored = false ) :StartScansResult {
					$this->startCalls[] = $scans;
					$this->resetIgnoreCalls[] = $resetIgnored;
					return $this->startResult;
				}
			},
		];

		PluginControllerInstaller::install( $controller );
		return $controller;
	}
}

class ScansStartUnderTest extends ScansStart {

	public ScansStartRecordingProgress $recordingProgress;

	protected function progress() :ScanProgressBuilder {
		return $this->recordingProgress;
	}
}

class ScansStartRecordingProgress extends ScanProgressBuilder {

	public const RUNNING = [ 'progress_state' => 'running', 'progress_html' => 'running-html', 'progress_detail' => 'File Guard 5%' ];
	public const FAILED = [ 'progress_state' => 'failed', 'progress_html' => 'failed-html', 'progress_detail' => '' ];

	public array $calls = [];

	public function forScans( array $trackedScanIDs ) :array {
		$this->calls[] = [ 'forScans', $trackedScanIDs ];
		return self::RUNNING;
	}

	public function forFailure( string $message ) :array {
		$this->calls[] = [ 'forFailure', $message ];
		return self::FAILED;
	}
}
