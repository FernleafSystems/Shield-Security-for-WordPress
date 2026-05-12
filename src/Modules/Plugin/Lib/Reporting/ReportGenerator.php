<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\FullPageDisplay\FullPageDisplayNonTerminating;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports as ReportsActions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Report\{
	SecurityReport,
	SecurityReportAlert
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Email\EmailVO;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\Reports\Ops as ReportsDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Uuid;

class ReportGenerator {

	use PluginControllerConsumer;

	public function auto() {
		( new AutoReportCoordinator() )->run();
	}

	/**
	 * @param array{areas:array<string,list<string>>} $options
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
	public function buildAndStore( ReportVO $report ) :ReportsDB\Record {
		$con = self::con();

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

		$this->prepareReportContracts( $report );
		$this->preRenderChecks( $report );
		if ( \count( $report->areas_data ) === 0 ) {
			throw new Exceptions\ReportDataEmptyException( 'empty report data' );
		}

		try {
			$payload = $con->action_router->action( FullPageDisplayNonTerminating::class, [
				'render_slug' => $this->getRenderSlugForReportType( $report->type ),
				'render_data' => [
					'report' => $report->getRawData(),
				],
			] )->payload();
			$report->content = (string)( $payload[ 'render_output' ] ?? '' );
		}
		catch ( ActionException $e ) {
			$report->content = $e->getMessage();
		}

		/** @var ReportsDB\Record $record */
		$record = $con->db_con->reports->getRecord();
		$record->interval_start_at = $report->start_at;
		$record->interval_end_at = $report->end_at;
		$record->interval_length = $report->interval ?? 'custom';
		$record->type = $report->type;
		$record->unique_id = ( new Uuid() )->V4();
		$record->protected = $record->type !== Constants::REPORT_TYPE_CUSTOM;
		$record->title = $report->title;
		$record->content = \function_exists( '\gzdeflate' ) ? \gzdeflate( $report->content ) : $report->content;

		$con->db_con->reports->getQueryInserter()->insert( $record );

		$con->comps->events->fireEvent( $this->getGeneratedReportEventKey( $report->type ), [
			'audit_params' => [
				'type'     => $con->comps->reports->getReportTypeName( $record->type ),
				'interval' => $record->interval_length,
			]
		] );

		return $record;
	}

	private function getGeneratedReportEventKey( string $reportType ) :string {
		return $reportType === Constants::REPORT_TYPE_ALERT ? 'report_generated_alert' : 'report_generated';
	}

	private function getRenderSlugForReportType( string $reportType ) :string {
		return $reportType === Constants::REPORT_TYPE_ALERT ? SecurityReportAlert::SLUG : SecurityReport::SLUG;
	}

	private function prepareReportContracts( ReportVO $report ) :void {
		if ( $report->type === Constants::REPORT_TYPE_ALERT ) {
			$report->alert_digest = ( new BuildAlertDigestContract() )->build( $report );
		}
		elseif ( $report->type === Constants::REPORT_TYPE_INFO ) {
			$report->info_headline = ( new BuildInfoHeadlineContract() )->build();
		}
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
		if ( $report->type === Constants::REPORT_TYPE_ALERT
			 && empty( $report->alert_digest[ 'has_new_items' ] ) ) {
			$data = [];
			$report->alert_digest = [];
		}
		elseif ( $report->type === Constants::REPORT_TYPE_INFO && $inspector->countAll() === 0 ) {
			// if there's nothing to report, don't create a report.
			$data = [];
		}
		$report->areas_data = $data;
	}

	public function sendNotificationEmail( ReportVO $report ) {
		$con = self::con();

		$isAlert = $report->type === Constants::REPORT_TYPE_ALERT;
		$subjectLabel = $isAlert
			? __( 'Security Alert', 'wp-simple-firewall' )
			: __( 'Security Report', 'wp-simple-firewall' );
		$renderClass = $isAlert
			? ReportsActions\Contexts\EmailReportAlert::class
			: ReportsActions\Contexts\EmailReportInfo::class;

		try {
			$email = EmailVO::Factory(
				$con->comps->opts_lookup->getReportEmail(),
				$subjectLabel.' - '.$con->labels->Name,
				$con->action_router->render(
					$renderClass,
					[
						'home_url'     => Services::WpGeneral()->getHomeUrl(),
						'report'       => $report,
						'detail_level' => 'detailed',
					]
				)
			);
			$email->is_alert = $isAlert;

			$con->email_con->sendVO( $email );

			$con->comps->events->fireEvent( 'report_sent', [
				'audit_params' => [
					'type'   => $con->comps->reports->getReportTypeName( $report->type ),
					'medium' => 'email',
				]
			] );
		}
		catch ( \Exception $e ) {
			error_log( $e->getMessage() );
		}
	}

	public function persistAlertNotifications( ReportVO $report ) :bool {
		$targetIDs = \array_values( \array_unique( \array_map(
			'intval',
			\array_filter(
				\is_array( $report->alert_digest[ 'notification_target_ids' ] ?? null )
					? $report->alert_digest[ 'notification_target_ids' ]
					: [],
				static fn( $id ) :bool => (int)$id > 0
			)
		) ) );

		if ( empty( $targetIDs ) ) {
			return false;
		}

		$updated = Services::WpDb()->doSql( sprintf(
			'UPDATE `%s` SET `notified_at`=%d WHERE `id` IN (%s) AND `notified_at`=0;',
			self::con()->db_con->scan_result_items->getTable(),
			Services::Request()->ts(),
			\implode( ',', $targetIDs )
		) );

		return \is_numeric( $updated ) && (int)$updated === \count( $targetIDs );
	}
}
