<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib;

use FernleafSystems\Utilities\Logic\OneTimeExecute;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\Reports as DBReports;
use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports\Build;
use FernleafSystems\Wordpress\Services\Services;

class ReportingController {

	use Modules\ModConsumer;
	use OneTimeExecute;

	/**
	 * @return bool
	 */
	protected function canRun() {
		/** @var Modules\Reporting\Options $opts */
		$opts = $this->getOptions();
		return $opts->getFrequencyInfo() !== 'disabled' || $opts->getFrequencyAlert() !== 'disabled';
	}

	protected function run() {
		add_action( $this->getCon()->prefix( 'hourly_cron' ), [ $this, 'runHourlyCron' ] );
	}

	public function runHourlyCron() {
		$this->buildAndSendReport();
	}

	private function buildAndSendReport() {
		/** @var Modules\Reporting\Options $opts */
		$opts = $this->getOptions();

		$aReports = [];

		if ( $opts->getFrequencyAlert() !== 'disabled' ) {
			try {
				$oAlertReport = $this->buildReportAlerts();
				if ( !empty( $oAlertReport->content ) ) {
					$this->storeReportRecord( $oAlertReport );
					$aReports[] = $oAlertReport;
				}
			}
			catch ( \Exception $e ) {
			}
		}

		if ( $opts->getFrequencyInfo() !== 'disabled' ) {
			try {
				$oInfoReport = $this->buildReportInfo();
				if ( !empty( $oInfoReport->content ) ) {
					$this->storeReportRecord( $oInfoReport );
					$aReports[] = $oInfoReport;
				}
			}
			catch ( \Exception $e ) {
			}
		}

		$this->sendEmail( $aReports );
	}

	/**
	 * @param Modules\Reporting\Lib\Reports\ReportVO $oReport
	 * @return bool
	 */
	private function storeReportRecord( Reports\ReportVO $oReport ) {
		$oRecord = new DBReports\EntryVO();
		$oRecord->sent_at = Services::Request()->ts();
		$oRecord->rid = $oReport->rid;
		$oRecord->type = $oReport->type;
		$oRecord->frequency = $oReport->interval;
		$oRecord->interval_end_at = $oReport->interval_end_at;

		/** @var \ICWP_WPSF_FeatureHandler_Reporting $oMod */
		$oMod = $this->getMod();
		return $oMod->getDbHandler_Reports()
					->getQueryInserter()
					->insert( $oRecord );
	}

	/**
	 * @return Modules\Reporting\Lib\Reports\ReportVO
	 * @throws \Exception
	 */
	private function buildReportAlerts() {
		$report = ( new Reports\CreateReportVO( DBReports\Handler::TYPE_ALERT ) )
			->setMod( $this->getMod() )
			->create();
		( new Build\BuilderAlerts( $report ) )
			->setMod( $this->getMod() )
			->build();
		return $report;
	}

	/**
	 * @return Modules\Reporting\Lib\Reports\ReportVO
	 * @throws \Exception
	 */
	private function buildReportInfo() {
		$report = ( new Reports\CreateReportVO( DBReports\Handler::TYPE_INFO ) )
			->setMod( $this->getMod() )
			->create();
		( new Build\BuilderInfo( $report ) )
			->setMod( $this->getMod() )
			->build();
		return $report;
	}

	/**
	 * @param Modules\Reporting\Lib\Reports\ReportVO[] $reportVOs
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
		}
	}
}