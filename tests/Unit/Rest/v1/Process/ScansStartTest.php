<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Rest\v1\Process;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Core\Rest\Exceptions\ApiException;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\StartScansResult;
use FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Process\ScansStart;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginControllerInstaller;

class ScansStartTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->returnArg();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_process_uses_bad_request_for_empty_selection() :void {
		$this->installController( StartScansResult::fromRequested( [] ) );

		$exception = $this->captureProcessException( [ 'scan_slugs' => [] ] );

		$this->assertSame( 400, $exception->getCode() );
		$this->assertSame( ScansStart::SUBCODE_NO_SELECTION, $exception->getSubErrorCode() );
	}

	public function test_process_uses_unavailable_when_start_is_blocked() :void {
		$this->installController( StartScansResult::fromRequested( [ 'afs' ] ), 0, true );

		$exception = $this->captureProcessException( [ 'scan_slugs' => [ 'afs' ] ] );

		$this->assertSame( 503, $exception->getCode() );
		$this->assertSame( ScansStart::SUBCODE_START_BLOCKED, $exception->getSubErrorCode() );
	}

	public function test_process_uses_conflict_when_selection_cannot_start() :void {
		$state = $this->installController(
			StartScansResult::fromRequested( [ 'afs' ] )
							 ->addFailure( 'afs', StartScansResult::REASON_ALREADY_EXISTS )
		);

		$exception = $this->captureProcessException( [ 'scan_slugs' => [ 'afs' ] ] );

		$this->assertSame( 409, $exception->getCode() );
		$this->assertSame( ScansStart::SUBCODE_START_FAILED, $exception->getSubErrorCode() );
		$this->assertSame( [ [ 'afs' ] ], $state->scans->startCalls );
	}

	public function test_process_delegates_active_scan_conflict_to_central_start_policy() :void {
		$state = $this->installController(
			StartScansResult::fromRequested( [ 'afs' ] )
							 ->addFailure( 'afs', StartScansResult::REASON_ALREADY_EXISTS ),
			1
		);

		$exception = $this->captureProcessException( [ 'scan_slugs' => [ 'afs' ] ] );

		$this->assertSame( 409, $exception->getCode() );
		$this->assertSame( ScansStart::SUBCODE_START_FAILED, $exception->getSubErrorCode() );
		$this->assertSame( [ [ 'afs' ] ], $state->scans->startCalls );
	}

	public function test_process_preserves_status_payload_shape_on_partial_success() :void {
		$state = $this->installController(
			StartScansResult::fromRequested( [ 'afs', 'wpv' ] )
							 ->addStarted( 'afs', 51 )
							 ->addFailure( 'wpv', StartScansResult::REASON_CREATE_FAILED )
		);

		$payload = $this->invokeProcess( [ 'scan_slugs' => [ 'afs', 'wpv' ] ] );

		$this->assertArrayHasKey( 'enqueued_count', $payload );
		$this->assertArrayHasKey( 'enqueued_status', $payload );
		$this->assertArrayHasKey( 'current_slug', $payload );
		$this->assertArrayHasKey( 'current_name', $payload );
		$this->assertArrayHasKey( 'progress', $payload );
		$this->assertArrayHasKey( 'message', $payload );
		$this->assertSame( [ [ 'afs', 'wpv' ] ], $state->scans->startCalls );
	}

	private function captureProcessException( array $params ) :ApiException {
		try {
			$this->invokeProcess( $params );
		}
		catch ( ApiException $e ) {
			return $e;
		}

		$this->fail( ApiException::class );
	}

	private function invokeProcess( array $params ) :array {
		$process = new ScansStart();
		$process->setWpRestRequest( new \WP_REST_Request( $params ) );

		$method = new \ReflectionMethod( ScansStart::class, 'process' );
		$method->setAccessible( true );
		return $method->invoke( $process );
	}

	private function installController(
		StartScansResult $result,
		int $enqueuedCount = 0,
		bool $blocked = false
	) :object {
		$scans = new class( $result, $blocked ) {
			public array $startCalls = [];

			private StartScansResult $result;
			private bool $blocked;

			public function __construct( StartScansResult $result, bool $blocked ) {
				$this->result = $result;
				$this->blocked = $blocked;
			}

			public function getStartBlockedMessage() :string {
				return $this->blocked ? 'blocked' : '';
			}

			public function startNewScans( array $scans ) :StartScansResult {
				$this->startCalls[] = $scans;
				return $this->result;
			}
		};
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->comps = (object)[
			'scans'     => $scans,
			'site_query' => new class( $enqueuedCount ) {
				private int $enqueuedCount;

				public function __construct( int $enqueuedCount ) {
					$this->enqueuedCount = $enqueuedCount;
				}

				public function scanRuntime() :array {
					return [
						'enqueued_count' => $this->enqueuedCount,
						'running_states' => [ 'afs' => $this->enqueuedCount > 0 ],
						'current_slug'   => $this->enqueuedCount > 0 ? 'afs' : '',
						'current_name'   => $this->enqueuedCount > 0 ? 'afs' : '',
						'progress'       => 0.5,
					];
				}
			},
		];

		PluginControllerInstaller::install( $controller );
		return (object)[
			'scans' => $scans,
		];
	}
}
