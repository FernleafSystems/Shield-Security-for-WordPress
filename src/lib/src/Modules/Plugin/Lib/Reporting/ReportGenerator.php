<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\FullPageDisplay\FullPageDisplayNonTerminating;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports as ReportsActions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Report\SecurityReport;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\DB\Report\Ops as ReportsDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Reports\Exceptions\{
	AttemptingToCreateDisabledReportException,
	AttemptingToCreateDuplicateReportException
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Reports\ReportVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Uuid;

class ReportGenerator {

	use ModConsumer;

	/**
	 * @throws \Exception
	 */
	public function adHoc( int $start, int $end, array $options ) :string {
		$report = new Reports\ReportVO();
		$report->interval_start_at = $start;
		$report->interval_end_at = $end;
		$report->areas = $options[ 'areas' ];
		$report->type = Constants::REPORT_TYPE_ADHOC;

		return $this->buildAndStore( $report )->unique_id;
	}

	private function buildAndStore( ReportVO $report ) :ReportsDB\Record {

		$report->content = self::con()->action_router->action( FullPageDisplayNonTerminating::class, [
			'render_slug' => SecurityReport::SLUG,
			'render_data' => [
				'report' => $report->getRawData(),
			]
		] )->action_response_data[ 'render_output' ];

		/** @var ReportsDB\Record $record */
		$record = $this->mod()->getDbH_ReportLogs()->getRecord();
		$record->interval_start_at = $report->interval_start_at;
		$record->interval_end_at = $report->interval_end_at;
		$record->interval_length = $report->interval ?? '';
		$record->type = $report->type;
		$record->unique_id = ( new Uuid() )->V4();
		$record->content = \function_exists( '\gzdeflate' ) ? \gzdeflate( $report->content ) : $report->content;
		$this->mod()->getDbH_ReportLogs()->getQueryInserter()->insert( $record );
		return $record;
	}

	public function auto() {
		$reports = $this->buildReports();
		if ( !empty( $reports ) ) {
			$this->sendEmail( $this->renderFinalReports( $reports ) );
		}
	}

	/**
	 * @return Reports\ReportVO[]
	 */
	private function buildReports() :array {
		/** @var Reports\ReportVO[] $reports */
		$reports = [];
		foreach ( [ Constants::REPORT_TYPE_INFO, Constants::REPORT_TYPE_ALERT ] as $reportType ) {
			try {
				$report = ( new Reports\CreateReportVO() )->create( $reportType );
				$this->buildAndStore( $report );

				if ( \strlen( $report->content ) > 0 ) {
					$reports[] = $report;
					self::con()->fireEvent( 'report_generated', [
						'audit_params' => [
							'type'     => $this->mod()
											   ->getReportingController()
											   ->getReportTypeName( $report->type ),
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
				'reports'  => \array_map(
					function ( $report ) {
						return $report->content;
					},
					$reports
				)
			]
		);
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
}