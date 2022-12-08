<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Crons\PluginCronsConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\Reports as DBReports;
use FernleafSystems\Wordpress\Plugin\Shield\Modules;
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
		$this->buildAndSendReports();
	}

	private function buildAndSendReports() {
		/** @var Reports\ReportVO[] $reports */
		$reports = [];
		foreach ( $this->getReportTypes() as $reportType ) {
			try {
				$report = ( new Reports\CreateReportVO() )
					->setMod( $this->getMod() )
					->create( $reportType );

				( new Reports\StandardReportBuilder() )
					->setMod( $this->getMod() )
					->build( $report );

				if ( !empty( $report->content ) ) {
					$reports[] = $report;
					$this->storeReportRecord(  $report );
					$this->getCon()->fireEvent( 'report_generated', [
						'audit_params' => [
							'type'     => $this->getReportTypeName( $report->type ),
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
	 * @return Reports\Reporters\BaseReporter[]
	 */
	public function getReporters( string $type ) :array {
		return array_map(
			function ( $reporter ) {
				/** @var Reports\Reporters\BaseReporter $reporter */
				$reporter = new $reporter();
				return $reporter->setCon( $this->getCon() );
			},
			array_filter(
				Constants::REPORTERS,
				function ( $reporter ) use ( $type ) {
					/** @var Reports\Reporters\BaseReporter $reporter */
					return $reporter::TYPE === $type;
				}
			)
		);
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