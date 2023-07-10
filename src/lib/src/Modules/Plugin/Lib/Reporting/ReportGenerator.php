<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports as ReportsActions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\DB\Report\Ops as ReportsDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Reports;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Reports\Exceptions\{
	AttemptingToCreateDisabledReportException,
	AttemptingToCreateDuplicateReportException
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class ReportGenerator {

	use ModConsumer;

	public function auto() {
		$reports = $this->buildReports();
		foreach ( $reports as $report ) {
			$this->storeReportRecord( $report );
			$this->con()->fireEvent( 'report_generated', [
				'audit_params' => [
					'type'     => $this->getReportTypeName( $report->type ),
					'interval' => $report->interval,
				]
			] );
		}
		if ( !empty( $reports ) ) {
			$this->sendEmail( $this->renderFinalReports( $reports ) );
		}
	}

	public function adHoc() :string {
		$r = $this->renderFinalReports( $this->buildReports() );
		$this->sendEmail( $r );
		return $r;
	}

	/**
	 * @return Reports\ReportVO[]
	 */
	private function buildReports() :array {
		/** @var Reports\ReportVO[] $reports */
		$reports = [];
		foreach ( \array_keys( $this->getReportTypes() ) as $reportType ) {
			try {
				$report = ( new Reports\CreateReportVO() )->create( $reportType );

				( new Reports\StandardReportBuilder() )->build( $report );

				if ( \strlen( $report->content ) > 0 ) {
					$reports[] = $report;
					$this->con()->fireEvent( 'report_generated', [
						'audit_params' => [
							'type'     => $this->getReportTypeName( $report->type ),
							'interval' => $report->interval,
						]
					] );
				}
			}
			catch ( AttemptingToCreateDuplicateReportException|AttemptingToCreateDisabledReportException $e ) {
//				error_log( $e->getMessage() );
			}
			catch ( \Exception $e ) {
				error_log( $e->getMessage() );
			}
		}

		return $reports;
	}

	/**
	 * @param Reports\ReportVO[] $reports
	 */
	private function renderFinalReports( array $reports, string $context = Constants::REPORT_CONTEXT_AUTO ) :string {

		switch ( $context ) {
			case Constants::REPORT_CONTEXT_AD_HOC:
			case Constants::REPORT_CONTEXT_AUTO:
			default:
				$renderer = ReportsActions\Contexts\EmailReport::SLUG;
				break;
		}

		return $this->con()->action_router->render(
			$renderer,
			[
				'home_url' => Services::WpGeneral()->getHomeUrl(),
				'reports'  => array_map(
					function ( $report ) {
						return $report->content;
					},
					$reports
				)
			]
		);
	}

	private function storeReportRecord( Reports\ReportVO $report ) :bool {
		$reportsDB = $this->con()->getModule_Plugin()->getDbH_ReportLogs();
		/** @var ReportsDB\Record $record */
		$record = $reportsDB->getRecord();
		$record->type = $report->type;
		$record->interval_length = $report->interval;
		$record->interval_end_at = $report->interval_end_at;
		return $reportsDB->getQueryInserter()->insert( $record );
	}

	private function sendEmail( string $report ) {
		try {
			$this->mod()
				 ->getEmailProcessor()
				 ->send(
					 $this->mod()->getPluginReportEmail(),
					 __( 'Site Report', 'wp-simple-firewall' ).' - '.$this->con()->getHumanName(),
					 $report
				 );

			$this->con()->fireEvent( 'report_sent', [
				'audit_params' => [
					'medium' => 'email',
				]
			] );
		}
		catch ( \Exception $e ) {
			error_log( $e->getMessage() );
		}
	}

	private function getReportTypes() :array {
		return [
			Constants::REPORT_TYPE_ALERT => 'alert',
			Constants::REPORT_TYPE_INFO  => 'info',
		];
	}

	private function getReportTypeName( string $type ) :string {
		return $this->getReportTypes()[ $type ] ?? 'invalid report type';
	}
}