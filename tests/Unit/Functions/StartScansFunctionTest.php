<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Functions;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Functions;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\StartScansResult;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginControllerInstaller;
use function FernleafSystems\Wordpress\Plugin\Shield\Functions\start_scans;

class StartScansFunctionTest extends BaseUnitTest {

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_start_scans_helper_delegates_to_central_start_when_capability_allows() :void {
		$state = $this->installController(
			true,
			StartScansResult::fromRequested( [ 'afs' ] )->addStarted( 'afs', 31 )
		);

		$result = start_scans( [ 'afs' ] );

		$this->assertSame( [ [ 'afs' ] ], $state->scans->startCalls );
		$this->assertSame( [ 31 ], $result->getStartedScanIDs() );
	}

	public function test_start_scans_helper_returns_scan_unavailable_when_capability_blocks() :void {
		$state = $this->installController(
			false,
			StartScansResult::fromRequested( [ 'afs' ] )->addStarted( 'afs', 31 )
		);

		$result = start_scans( [ 'afs' ] );

		$this->assertSame( [], $state->scans->startCalls );
		$this->assertFalse( $result->hasStarted() );
		$this->assertSame( [ StartScansResult::REASON_SCAN_UNAVAILABLE ], \array_column( $result->getFailures(), 'reason' ) );
	}

	private function installController( bool $hasScanCap, StartScansResult $result ) :object {
		$scans = new class( $result ) {
			public array $startCalls = [];

			private StartScansResult $result;

			public function __construct( StartScansResult $result ) {
				$this->result = $result;
			}

			public function startNewScans( array $scans ) :StartScansResult {
				$this->startCalls[] = $scans;
				return $this->result;
			}
		};

		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->caps = new class( $hasScanCap ) {
			private bool $hasScanCap;

			public function __construct( bool $hasScanCap ) {
				$this->hasScanCap = $hasScanCap;
			}

			public function hasCap( string $cap ) :bool {
				return $cap === 'scan_frequent' && $this->hasScanCap;
			}
		};
		$controller->comps = (object)[
			'scans' => $scans,
		];

		PluginControllerInstaller::install( $controller );
		return (object)[
			'scans' => $scans,
		];
	}
}
