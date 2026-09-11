<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan;

class ScansControllerNotificationReadinessTestLog {

	public static array $messages = [];
}

if ( !\function_exists( __NAMESPACE__.'\\error_log' ) ) {
	function error_log( string $message ) :bool {
		ScansControllerNotificationReadinessTestLog::$messages[] = $message;
		if ( \class_exists( '\\FernleafSystems\\Wordpress\\Plugin\\Shield\\Tests\\Unit\\Modules\\HackGuard\\Scan\\ScansCronStartLogSpy' ) ) {
			\FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Scan\ScansCronStartLogSpy::record( $message );
		}
		return true;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Scan;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\ScansController;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Scan\Queue\Support\ScanQueueLifecycleHarness;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState
};
use FernleafSystems\Wordpress\Services\Core\Db;

class ScansControllerNotificationReadinessTest extends BaseUnitTest {

	private array $servicesSnapshot = [];
	private bool $hadWpdb = false;
	private $wpdbSnapshot;

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		$this->hadWpdb = \array_key_exists( 'wpdb', $GLOBALS );
		$this->wpdbSnapshot = $GLOBALS[ 'wpdb' ] ?? null;
		$GLOBALS[ 'wpdb' ] = (object)[ 'last_error' => '' ];
		\FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\ScansControllerNotificationReadinessTestLog::$messages = [];
		if ( \class_exists( ScansCronStartLogSpy::class ) ) {
			ScansCronStartLogSpy::reset();
		}
	}

	protected function tearDown() :void {
		if ( $this->hadWpdb ) {
			$GLOBALS[ 'wpdb' ] = $this->wpdbSnapshot;
		}
		else {
			unset( $GLOBALS[ 'wpdb' ] );
		}
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_active_scan_short_circuits_asset_readiness() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$harness->insertScan( [ 'scan' => 'wpv', 'status' => 'running' ] );
		$assets = new NotificationReadinessAssets( false );
		$harness->controller->comps->asset_coordinator = $assets;

		$this->assertFalse( ( new ScansController() )->isReadyForScanResultNotifications() );
		$this->assertSame( 0, $assets->calls );
	}

	public function test_retryable_assets_block_and_idle_state_is_ready() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$assets = new NotificationReadinessAssets( true );
		$harness->controller->comps->asset_coordinator = $assets;
		$harness->sql->resetQueryLog();
		$scans = new ScansController();

		$this->assertFalse( $scans->isReadyForScanResultNotifications() );
		$assets->hasWork = false;
		$this->assertTrue( $scans->isReadyForScanResultNotifications() );
		$this->assertSame( 2, $assets->calls );
		$this->assertCount( 2, $harness->sql->queryLog() );
	}

	public function test_each_decision_reads_fresh_persisted_scan_state() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$assets = new NotificationReadinessAssets( false );
		$harness->controller->comps->asset_coordinator = $assets;
		$harness->sql->resetQueryLog();
		$scans = new ScansController();

		$this->assertTrue( $scans->isReadyForScanResultNotifications() );
		$harness->insertScan( [ 'scan' => 'apc', 'status' => 'queued' ] );
		$this->assertFalse( $scans->isReadyForScanResultNotifications() );
		$this->assertCount( 2, \array_filter(
			$harness->sql->queryLog(),
			static fn( string $query ) :bool => \strpos( $query, 'SELECT ' ) === 0
		) );
		$this->assertSame( 1, $assets->calls );
	}

	public function test_scan_query_failure_fails_closed_with_bounded_diagnostic() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$harness->controller->comps->asset_coordinator = new NotificationReadinessAssets( false );
		ServicesState::mergeItems( [
			'service_wpdb' => new NotificationReadinessScanDb( new \RuntimeException( \str_repeat( "failure \n", 100 ) ) ),
		] );

		$this->assertFalse( ( new ScansController() )->isReadyForScanResultNotifications() );

		$messages = $this->readinessLogMessages();
		$this->assertCount( 1, $messages );
		$this->assertStringStartsWith( 'Shield scan-result notification readiness check failed: ', $messages[ 0 ] );
		$this->assertStringNotContainsString( "\n", $messages[ 0 ] );
		$this->assertLessThanOrEqual( 356, \strlen( $messages[ 0 ] ) );
	}

	public function test_asset_readiness_failure_fails_closed() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$assets = new NotificationReadinessAssets( false );
		$assets->error = new \RuntimeException( 'Synthetic asset readiness failure.' );
		$harness->controller->comps->asset_coordinator = $assets;

		$this->assertFalse( ( new ScansController() )->isReadyForScanResultNotifications() );
		$this->assertSame( 1, $assets->calls );
		$this->assertStringContainsString(
			'Synthetic asset readiness failure.',
			$this->readinessLogMessages()[ 0 ]
		);
	}

	public function test_readiness_signal_is_a_zero_payload_contract() :void {
		$this->assertSame(
			'shield/scan_result_notification_readiness_opened',
			ScansController::HOOK_SCAN_RESULT_NOTIFICATION_READINESS_OPENED
		);
	}

	private function readinessLogMessages() :array {
		$messages = \FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\ScansControllerNotificationReadinessTestLog::$messages;
		if ( empty( $messages ) && \class_exists( ScansCronStartLogSpy::class ) ) {
			$messages = ScansCronStartLogSpy::$messages;
		}
		return $messages;
	}
}

class NotificationReadinessAssets {

	public bool $hasWork;
	public int $calls = 0;
	public ?\Throwable $error = null;

	public function __construct( bool $hasWork ) {
		$this->hasWork = $hasWork;
	}

	public function hasRetryableAssetWork() :bool {
		$this->calls++;
		if ( $this->error !== null ) {
			throw $this->error;
		}
		return $this->hasWork;
	}
}

class NotificationReadinessScanDb extends Db {

	private \Throwable $error;

	public function __construct( \Throwable $error ) {
		$this->error = $error;
	}

	public function selectCustom( $query, $format = null ) {
		unset( $query, $format );
		throw $this->error;
	}
}
