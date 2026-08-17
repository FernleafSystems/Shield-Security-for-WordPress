<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use Carbon\Carbon;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	ActionProcessor,
	Actions\ReportCreateCustom,
	Exceptions\InvalidActionNonceException
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\ScanStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\ActionRequestNonceFixture;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\CompiledReportAssetFixture;
use FernleafSystems\Wordpress\Services\Services;

class ReportCreateCustomIntegrationTest extends ShieldIntegrationTestCase {

	use ActionRequestNonceFixture;

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'reports' );
		$this->requireDb( 'events' );
		$this->requireDb( 'scans' );
		$this->loginAsSecurityAdmin();
		$con = $this->requireController();
		$con->this_req->wp_is_ajax = false;
		$con->comps->asset_coordinator->deleteState();
		\wp_clear_scheduled_hook( $con->prefix( 'asset_coordinator' ) );
	}

	public function tear_down() {
		if ( static::con() !== null ) {
			self::con()->comps->asset_coordinator->deleteState();
			\wp_clear_scheduled_hook( self::con()->prefix( 'asset_coordinator' ) );
		}
		parent::tear_down();
	}

	public function test_create_custom_report_requires_valid_nonce_before_report_creation() :void {
		$before = $this->countReports();
		$snapshot = $this->seedActionNonceContext( ReportCreateCustom::class );
		$this->mergeCurrentRequestTransport( [
			ActionData::FIELD_NONCE => '',
		] );

		try {
			$this->expectException( InvalidActionNonceException::class );
			$this->processor()->processAction( ReportCreateCustom::SLUG, [] );
		}
		finally {
			$this->assertSame( $before, $this->countReports() );
			$this->restoreActionNonceContext( $snapshot );
		}
	}

	public function test_create_custom_report_normalizes_malformed_area_inputs_before_generation() :void {
		$before = $this->countReports();
		$this->requireController()->this_req->wp_is_ajax = true;
		$this->mergeCurrentRequestTransport( [
			'form_params' => [
				'title'            => 'Malformed Area Inputs',
				'start_date'       => '2026-01-01',
				'end_date'         => '2026-01-02',
				'changes_zones'    => 'plugins',
				'statistics_zones' => 'security',
				'scans_zones'      => 'scan_results',
			],
		] );

		$payload = $this->processor()->processAction(
			ReportCreateCustom::SLUG,
			ActionData::Build( ReportCreateCustom::class, true )
		)->payload();

		$this->assertFalse( $payload[ 'success' ] ?? true );
		$this->assertSame( $before, $this->countReports() );
	}

	public function test_create_custom_report_preserves_custom_interval_contract() :void {
		$before = $this->countReports();
		$this->insertActiveScan( 'afs', ScanStatus::BUILT );
		$this->assertTrue( self::con()->comps->asset_coordinator->enqueueAsset(
			'plugin',
			self::con()->base_file,
			60
		) );
		$this->assertFalse( self::con()->comps->scans->isReadyForScanResultNotifications() );
		$start = Carbon::create( 2026, 1, 1, 0, 0, 0, \wp_timezone() );
		$end = Carbon::create( 2026, 1, 2, 23, 59, 59, \wp_timezone() );
		$this->insertEvent( 'ip_blocked', 2, ( clone $start )->addHour()->timestamp );
		$this->insertEvent( 'ip_blocked', 3, ( clone $start )->addHours( 2 )->timestamp );
		$this->assertTrue( self::con()->db_con->events->compactBoundary( $start->timestamp, $end->timestamp ) );
		$this->captureShieldEvents();
		$this->requireController()->this_req->wp_is_ajax = true;
		$this->mergeCurrentRequestTransport( [
			'form_params' => [
				'title'            => 'Custom Interval Contract',
				'start_date'       => '2026-01-01',
				'end_date'         => '2026-01-02',
				'statistics_zones' => [ 'security' ],
			],
		] );

		CompiledReportAssetFixture::ensureReady( self::con()->getRootDir() );
		$payload = $this->processor()->processAction(
			ReportCreateCustom::SLUG,
			ActionData::Build( ReportCreateCustom::class, true )
		)->payload();

		$this->assertTrue( $payload[ 'success' ] );
		$this->assertSame( $before + 1, $this->countReports() );
		$report = self::con()->db_con->reports->getQuerySelector()
			->filterByType( Constants::REPORT_TYPE_CUSTOM )
			->setOrderBy( 'id' )
			->first();
		$this->assertSame( Constants::REPORT_TYPE_CUSTOM, $report->type );
		$this->assertSame( Constants::REPORT_INTERVAL_CUSTOM, $report->interval_length );
		$this->assertSame( $start->timestamp, $report->interval_start_at );
		$this->assertSame( $end->timestamp, $report->interval_end_at );
		$this->assertNotSame( '', (string)$report->content );
		$content = \function_exists( '\\gzinflate' ) ? \gzinflate( $report->content ) : $report->content;
		$this->assertIsString( $content );
		$events = $this->getCapturedEventsByKey( 'report_generated' );
		$this->assertCount( 1, $events );
		$this->assertSame(
			Constants::REPORT_INTERVAL_CUSTOM,
			$events[ 0 ][ 'meta' ][ 'audit_params' ][ 'interval' ]
		);
	}

	private function insertEvent( string $event, int $count, int $createdAt ) :void {
		$dbh = self::con()->db_con->events;
		$record = $dbh->getRecord();
		$record->event = $event;
		$record->count = $count;
		$record->created_at = $createdAt;
		$dbh->getQueryInserter()->insert( $record );
	}

	private function processor() :ActionProcessor {
		return new ActionProcessor();
	}

	private function insertActiveScan( string $scanSlug, string $status ) :int {
		$now = Services::Request()->ts();
		$dbh = self::con()->db_con->scans;
		$record = $dbh->getRecord();
		$record->scan = $scanSlug;
		$record->status = $status;
		$record->scope_type = 'full';
		$record->scope_key = '';
		$record->run_trigger = 'manual';
		$record->started_at = $now;
		$record->last_process_at = $now;
		$record->ready_at = $now;
		$record->finished_at = 0;
		$dbh->getQueryInserter()->insert( $record );
		return (int)Services::WpDb()->getVar( 'SELECT LAST_INSERT_ID()' );
	}

	private function countReports() :int {
		return self::con()->db_con->reports->getQuerySelector()->count();
	}
}
