<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Components\Reports as ReportsActions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\DB\Report\Ops as ReportsDB;
use FernleafSystems\Wordpress\Services\Services;

class ReportGenerator {

	use Modules\ModConsumer;

	public function auto() {
		$reports = $this->buildReports();
		foreach ( $reports as $report ) {
			$this->storeReportRecord( $report );
			$this->getCon()->fireEvent( 'report_generated', [
				'audit_params' => [
					'type'     => $this->getReportTypeName( $report->type ),
					'interval' => $report->interval,
				]
			] );
		}
		$this->sendEmail( $this->renderFinalReports( $reports ) );
	}

	public function adHoc() {
		echo $this->renderFinalReports( $this->buildReports() );
	}

	/**
	 * @return Reports\ReportVO[]
	 */
	private function buildReports() :array {
		/** @var Reports\ReportVO[] $reports */
		$reports = [];
		foreach ( array_keys( $this->getReportTypes() ) as $reportType ) {
			try {
				$report = ( new Reports\CreateReportVO() )
					->setMod( $this->getMod() )
					->create( $reportType );
				( new Reports\StandardReportBuilder() )
					->setMod( $this->getMod() )
					->build( $report );

				if ( strlen( $report->content ) > 0 ) {
					$reports[] = $report;
					$this->getCon()->fireEvent( 'report_generated', [
						'audit_params' => [
							'type'     => $this->getReportTypeName( $report->type ),
							'interval' => $report->interval,
						]
					] );
				}
			}
			catch ( \Exception $e ) {
				error_log( $e->getMessage() );
			}
		}

		return $reports;
	}

	private function renderFinalReports( array $reports, string $context = Constants::REPORT_CONTEXT_AUTO ) :string {

		switch ( $context ) {
			case Constants::REPORT_CONTEXT_AD_HOC:
				$renderer = ReportsActions\Contexts\EmailReport::SLUG;
				break;
			case Constants::REPORT_CONTEXT_AUTO:
			default:
				$renderer = ReportsActions\Contexts\EmailReport::SLUG;
				break;
		}

		return $this->getCon()->action_router->render(
			$renderer,
			[
				'home_url' => Services::WpGeneral()->getHomeUrl(),
				'reports'  => $reports
			]
		);
	}

	private function storeReportRecord( Reports\ReportVO $report ) :bool {
		/** @var Modules\Reporting\ModCon $mod */
		$mod = $this->getMod();
		$reportsDB = $mod->getDbHandler_ReportLogs();
		/** @var ReportsDB\Record $record */
		$record = $reportsDB->getRecord();
		$record->type = $report->type;
		$record->interval_length = $report->interval;
		$record->interval_end_at = $report->interval_end_at;
		return $reportsDB->getQueryInserter()->insert( $record );
	}

	private function sendEmail( string $report ) {
		try {
			$this->getMod()
				 ->getEmailProcessor()
				 ->send(
					 $this->getMod()->getPluginReportEmail(),
					 __( 'Site Report', 'wp-simple-firewall' ).' - '.$this->getCon()->getHumanName(),
					 $report
				 );

			$this->getCon()->fireEvent( 'report_sent', [
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