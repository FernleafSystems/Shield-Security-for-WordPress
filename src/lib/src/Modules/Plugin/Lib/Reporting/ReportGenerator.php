<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\FullPageDisplay\FullPageDisplayNonTerminating;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports as ReportsActions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Report\SecurityReport;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\FileLocker\Ops as FileLockerDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops\LoadFileLocks;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\DB\Reports\Ops as ReportsDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Uuid;

class ReportGenerator {

	use ModConsumer;

	public function auto() {
		$reports = [];

		try {
			$info = ( new CreateReportVO() )->create( Constants::REPORT_TYPE_INFO );
			$info->record = $this->buildAndStore( $info );
			$reports[] = $info;
		}
		catch ( Exceptions\ReportBuildException $e ) {
		}

		try {
			$alert = ( new CreateReportVO() )->create( Constants::REPORT_TYPE_ALERT );
			$alert->record = $this->buildAndStore( $alert );
			\array_unshift( $reports, $alert );
			$this->markAlertsAsNotified();
		}
		catch ( Exceptions\ReportBuildException $e ) {
//			error_log( $e->getMessage() );
		}

		if ( !empty( $reports ) ) {
			$this->sendNotificationEmail( $reports );
		}
	}

	/**
	 * @throws Exceptions\ReportDataEmptyException
	 */
	public function custom( string $title, int $start, int $end, array $options ) :ReportsDB\Record {
		$report = new ReportVO();
		$report->type = Constants::REPORT_TYPE_CUSTOM;
		$report->title = $title;
		$report->start_at = $start;
		$report->end_at = $end;
		$report->areas = $options[ 'areas' ];
		return $this->buildAndStore( $report );
	}

	/**
	 * @throws Exceptions\ReportDataEmptyException
	 */
	private function buildAndStore( ReportVO $report ) :ReportsDB\Record {

		$areasData = [];
		foreach ( $report->areas as $slug => $area ) {
			switch ( $slug ) {
				case Constants::REPORT_AREA_CHANGES:
					$builder = new Data\BuildForChanges( $report );
					break;
				case Constants::REPORT_AREA_SCANS:
					$builder = new Data\BuildForScans( $report );
					break;
				case Constants::REPORT_AREA_STATS:
					$builder = new Data\BuildForStats( $report );
					break;
				default:
					error_log( 'unsupported report area slug: '.$slug );
					$builder = null;
					continue( 2 );
			}
			$areasData[ $slug ] = $builder->build();
		}
		$report->areas_data = $areasData;

		$this->preRenderChecks( $report );
		if ( \count( $report->areas_data ) === 0 ) {
			throw new Exceptions\ReportDataEmptyException( 'empty report data' );
		}

		try {
			$report->content = self::con()->action_router->action( FullPageDisplayNonTerminating::class, [
				'render_slug' => SecurityReport::SLUG,
				'render_data' => [
					'report' => $report->getRawData(),
				],
			] )->action_response_data[ 'render_output' ];
		}
		catch ( ActionException $e ) {
			$report->content = $e->getMessage();
		}

		/** @var ReportsDB\Record $record */
		$record = $this->mod()->getDbH_Reports()->getRecord();
		$record->interval_start_at = $report->start_at;
		$record->interval_end_at = $report->end_at;
		$record->interval_length = $report->interval ?? 'custom';
		$record->type = $report->type;
		$record->unique_id = ( new Uuid() )->V4();
		$record->protected = $record->type !== Constants::REPORT_TYPE_CUSTOM;
		$record->title = $report->title;
		$record->content = \function_exists( '\gzdeflate' ) ? \gzdeflate( $report->content ) : $report->content;
		$this->mod()->getDbH_Reports()->getQueryInserter()->insert( $record );

		self::con()->fireEvent( 'report_generated', [
			'audit_params' => [
				'type'     => $this->mod()
								   ->getReportingController()
								   ->getReportTypeName( $record->type ),
				'interval' => $record->interval_length,
			]
		] );

		return $record;
	}

	/**
	 * As the particular content for each report evolves, use this to filter out un-required data depending on the
	 * report type, and any other factors.
	 *
	 * The aim is to not generate, and certainly to not email out, "empty" useless reports.
	 */
	private function preRenderChecks( ReportVO $report ) :void {
		$data = $report->areas_data;
		$inspector = new ReportDataInspector( $data );
		if ( $report->type === Constants::REPORT_TYPE_ALERT && $inspector->countScanResultsNew() === 0 ) {
			unset( $data[ Constants::REPORT_AREA_SCANS ] );
		}
		elseif ( $report->type === Constants::REPORT_TYPE_INFO && $inspector->countAll() === 0 ) {
			// if there's nothing to report, don't create a report.
			$data = [];
		}
		$report->areas_data = $data;
	}

	private function sendNotificationEmail( array $reports ) {
		$con = self::con();

		try {
			$this->mod()
				 ->getEmailProcessor()
				 ->send(
					 $this->mod()->getPluginReportEmail(),
					 __( 'Security Report', 'wp-simple-firewall' ).' - '.$con->getHumanName(),
					 $con->action_router->render(
						 ReportsActions\Contexts\EmailReport::SLUG,
						 [
							 'home_url' => Services::WpGeneral()->getHomeUrl(),
							 'reports'  => $reports,
						 ]
					 )
				 );

			$con->fireEvent( 'report_sent', [
				'audit_params' => [
					'medium' => 'email',
				]
			] );
		}
		catch ( \Exception $e ) {
			error_log( $e->getMessage() );
		}
	}

	private function markAlertsAsNotified() {
		$modHG = self::con()->getModule_HackGuard();

		// File Locker
		/** @var FileLockerDB\Update $updater */
		$updater = $modHG->getDbH_FileLocker()->getQueryUpdater();
		foreach ( ( new LoadFileLocks() )->withProblemsNotNotified() as $record ) {
			$updater->markNotified( $record );
		}
		$modHG->getFileLocker()->clearLocks();

		// Standard Scan Results
		$modHG->getDbH_ResultItems()
			  ->getQueryUpdater()
			  ->setUpdateWheres( [
				  'notified_at' => 0,
			  ] )
			  ->setUpdateData( [
				  'notified_at' => Services::Request()->ts()
			  ] )
			  ->query();
	}
}