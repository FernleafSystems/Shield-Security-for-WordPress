<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Crons\PluginCronsConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\Reports as DBReports;
use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports\Build;
use FernleafSystems\Wordpress\Services\Services;

class ReportingController extends Modules\Base\Common\ExecOnceModConsumer {

	use PluginCronsConsumer;

	protected function canRun() :bool {
		/** @var Modules\Reporting\Options $opts */
		$opts = $this->getOptions();
		return $opts->getFrequencyInfo() !== 'disabled' || $opts->getFrequencyAlert() !== 'disabled';
	}

	protected function run() {
		$this->setupCronHooks();
	}

	public function runHourlyCron() {
		$this->buildAndSendReport();
	}

	private function buildAndSendReport() {
		/** @var Modules\Reporting\Options $opts */
		$opts = $this->getOptions();

		$reports = [];

		if ( $opts->getFrequencyAlert() !== 'disabled' ) {
			try {
				$report = $this->buildReportAlerts();
				if ( !empty( $report->content ) ) {
					$this->storeReportRecord( $report );
					$reports[] = $report;
					$this->getCon()->fireEvent( 'report_generated', [
						'audit_params' => [
							'type'     => 'alert',
							'interval' => $report->interval,
						]
					] );
				}
			}
			catch ( \Exception $e ) {
			}
		}

		if ( $opts->getFrequencyInfo() !== 'disabled' ) {
			try {
				$report = $this->buildReportInfo();
				if ( !empty( $report->content ) ) {
					$this->storeReportRecord( $report );
					$reports[] = $report;
					$this->getCon()->fireEvent( 'report_generated', [
						'audit_params' => [
							'type'     => 'info',
							'interval' => $report->interval,
						]
					] );
				}
			}
			catch ( \Exception $e ) {
			}
		}

		$this->sendEmail( $reports );
	}

	/**
	 * @param Modules\Reporting\Lib\Reports\ReportVO $report
	 * @return bool
	 */
	private function storeReportRecord( Reports\ReportVO $report ) :bool {
		$record = new DBReports\EntryVO();
		$record->sent_at = Services::Request()->ts();
		$record->rid = $report->rid;
		$record->type = $report->type;
		$record->frequency = $report->interval;
		$record->interval_end_at = $report->interval_end_at;

		/** @var Modules\Reporting\ModCon $mod */
		$mod = $this->getMod();
		return $mod->getDbHandler_Reports()
				   ->getQueryInserter()
				   ->insert( $record );
	}

	/**
	 * @throws \Exception
	 */
	private function buildReportAlerts() :Reports\ReportVO {
		$report = ( new Reports\CreateReportVO( DBReports\Handler::TYPE_ALERT ) )
			->setMod( $this->getMod() )
			->create();
		( new Build\BuilderAlerts( $report ) )
			->setMod( $this->getMod() )
			->build();
		return $report;
	}

	/**
	 * @throws \Exception
	 */
	private function buildReportInfo() :Reports\ReportVO {
		$report = ( new Reports\CreateReportVO( DBReports\Handler::TYPE_INFO ) )
			->setMod( $this->getMod() )
			->create();
		( new Build\BuilderInfo( $report ) )
			->setMod( $this->getMod() )
			->build();
		return $report;
	}

	/**
	 * @param Reports\ReportVO[] $reportVOs
	 */
	private function sendEmail( array $reportVOs ) {

		$reports = array_filter( array_map(
			function ( $rep ) {
				return $rep->content;
			},
			$reportVOs
		) );

		if ( !empty( $reports ) ) {
			try {
				$this->getMod()
					 ->getEmailProcessor()
					 ->send(
						 $this->getMod()->getPluginReportEmail(),
						 __( 'Site Report', 'wp-simple-firewall' ).' - '.$this->getCon()->getHumanName(),
						 $this->getCon()
							  ->getModule_Insights()
							  ->getActionRouter()
							  ->render( Modules\Insights\ActionRouter\Actions\Render\Components\Email\PluginReport::SLUG, [
								  'home_url' => Services::WpGeneral()->getHomeUrl(),
								  'reports'  => $reports
							  ] )
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
	}
}