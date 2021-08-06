<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Crons\PluginCronsConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\Reports as DBReports;
use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports\Build;
use FernleafSystems\Wordpress\Services\Services;

class ReportingController {

	use Modules\ModConsumer;
	use ExecOnce;
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
			$WP = Services::WpGeneral();
			try {
				$this->getMod()
					 ->getEmailProcessor()
					 ->sendEmailWithTemplate(
						 '/email/reports/cron_alert_info_report',
						 $this->getMod()->getPluginReportEmail(),
						 __( 'Site Report', 'wp-simple-firewall' ).' - '.$this->getCon()->getHumanName(),
						 [
							 'content' => [
								 'reports' => $reports
							 ],
							 'vars'    => [
								 'site_url'    => $WP->getHomeUrl(),
								 'report_date' => $WP->getTimeStampForDisplay(),
							 ],
							 'hrefs'   => [
								 'click_adjust' => $this->getCon()
														->getModule_Reporting()
														->getUrl_AdminPage()
							 ],
							 'strings' => [
								 'please_find'  => __( 'Please find your site report below.', 'wp-simple-firewall' ),
								 'depending'    => __( 'Depending on your settings and cron timings, this report may contain a combination of alerts, statistics and other information.', 'wp-simple-firewall' ),
								 'site_url'     => __( 'Site URL', 'wp-simple-firewall' ),
								 'report_date'  => __( 'Report Generation Date', 'wp-simple-firewall' ),
								 'use_links'    => __( 'Please use links provided in each section to review the report details.', 'wp-simple-firewall' ),
								 'click_adjust' => __( 'Click here to adjust your reporting settings', 'wp-simple-firewall' ),
							 ]
						 ]
					 );

				$this->getCon()->fireEvent( 'report_sent', [
					'audit_params' => [
						'medium'     => 'email',
					]
				] );
			}
			catch ( \Exception $e ) {
				error_log( $e->getMessage() );
			}
		}
	}
}