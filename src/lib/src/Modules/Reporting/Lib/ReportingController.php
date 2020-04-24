<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Reports as DBReports;
use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports\Build;
use FernleafSystems\Wordpress\Services\Services;

class ReportingController {

	use Modules\ModConsumer;
	use Modules\Base\OneTimeExecute;

	/**
	 * @return bool
	 */
	protected function canRun() {
		/** @var Modules\Reporting\Options $oOpts */
		$oOpts = $this->getOptions();
		return $oOpts->getFrequencyInfo() !== 'disabled' || $oOpts->getFrequencyAlerts() !== 'disabled';
	}

	protected function run() {
		add_action( $this->getCon()->prefix( 'hourly_cron' ), [ $this, 'runHourlyCron' ] );
	}

	public function runHourlyCron() {
		$this->buildAndSendReport();
	}

	private function buildAndSendReport() {
		/** @var Modules\Reporting\Options $oOpts */
		$oOpts = $this->getOptions();

		$aReports = [];

		if ( $oOpts->getFrequencyAlerts() !== 'disabled' ) {
			try {
				$oAlertReport = $this->buildReportAlerts();
				if ( !empty( $oAlertReport->content ) ) {
					$this->storeReportRecord( $oAlertReport );
					$aReports[] = $oAlertReport;
				}
			}
			catch ( \Exception $oE ) {
			}
		}

		if ( $oOpts->getFrequencyInfo() !== 'disabled' ) {
			try {
				$oInfoReport = $this->buildReportInfo();
				if ( !empty( $oInfoReport->content ) ) {
					$this->storeReportRecord( $oInfoReport );
					$aReports[] = $oInfoReport;
				}
			}
			catch ( \Exception $oE ) {
			}
		}

		$this->sendEmail( $aReports );
	}

	/**
	 * @param \FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports\ReportVO $oReport
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
	 * @return \FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports\ReportVO
	 * @throws \Exception
	 */
	private function buildReportAlerts() {
		$oReport = ( new Reports\CreateReportVO( DBReports\Handler::TYPE_ALERT ) )
			->setMod( $this->getMod() )
			->create();
		( new Build\BuilderAlerts( $oReport ) )
			->setMod( $this->getMod() )
			->build();
		return $oReport;
	}

	/**
	 * @return \FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports\ReportVO
	 * @throws \Exception
	 */
	private function buildReportInfo() {
		$oReport = ( new Reports\CreateReportVO( DBReports\Handler::TYPE_INFO ) )
			->setMod( $this->getMod() )
			->create();
		( new Build\BuilderInfo( $oReport ) )
			->setMod( $this->getMod() )
			->build();
		return $oReport;
	}

	/**
	 * @param \FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports\ReportVO[] $aReportVOs
	 */
	private function sendEmail( array $aReportVOs ) {

		$aReports = array_filter( array_map(
			function ( $oReport ) {
				return $oReport->content;
			},
			$aReportVOs
		) );

		if ( !empty( $aReports ) ) {
			$oWP = Services::WpGeneral();
			$aReports = array_merge(
				[
					__( 'Please find your site report below.', 'wp-simple-firewall' ),
					__( 'Depending on your settings and cron timings, this report may contain a combination of alerts, statistics and other information.', 'wp-simple-firewall' ),
					'',
					sprintf( '- %s: %s', __( 'Site URL', 'wp-simple-firewall' ), $oWP->getHomeUrl() ),
					sprintf( '- %s: %s', __( 'Report Generation Date', 'wp-simple-firewall' ),
						$oWP->getTimeStampForDisplay() ),
					'',
					__( 'Please use the links provided to review the report details.', 'wp-simple-firewall' ),
				],
				$aReports,
				[
					__( 'Thank You.', 'wp-simple-firewall' ),
				]
			);
			$this->getMod()
				 ->getEmailProcessor()
				 ->sendEmailWithWrap(
					 $this->getMod()->getPluginReportEmail(),
					 __( 'Site Report', 'wp-simple-firewall' ).' - '.$this->getCon()->getHumanName(),
					 $aReports
				 );
		}
	}
}