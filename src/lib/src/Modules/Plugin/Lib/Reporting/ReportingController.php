<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\FullPageDisplay\DisplayReport;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\FullPageDisplay\FullPageDisplayNonTerminating;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports\Components\BaseBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Report\SecurityReport;
use FernleafSystems\Wordpress\Plugin\Shield\Crons\PluginCronsConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\DB\Report\Ops as ReportDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModConsumer;
use FernleafSystems\Wordpress\Services\Utilities\Uuid;

class ReportingController {

	use ExecOnce;
	use ModConsumer;
	use PluginCronsConsumer;

	protected function canRun() :bool {
		return $this->opts()->getReportFrequencyInfo() !== 'disabled'
			   || $this->opts()->getReportFrequencyAlert() !== 'disabled';
	}

	protected function run() {
		$this->setupCronHooks();
	}

	public function runHourlyCron() {
		( new ReportGenerator() )->auto();
	}

	public function viewReportURL( string $uniqueReportID ) :string {
		return self::con()
			->plugin_urls
			->noncedPluginAction(
				DisplayReport::class,
				null,
				[
					'report_unique_id' => $uniqueReportID,
				]
			);
	}

	public function newReport( int $start, int $end, array $options ) :string {
		$report = new Reports\ReportVO();
		$report->interval_start_at = $start;
		$report->interval_end_at = $end;
		$report->areas = $options[ 'areas' ];

		$response = self::con()->action_router->action( FullPageDisplayNonTerminating::class, [
			'render_slug' => SecurityReport::SLUG,
			'render_data' => [
				'report' => $report->getRawData(),
			]
		] );

		$dbh = $this->mod()->getDbH_ReportLogs();
		/** @var ReportDB\Record $record */
		$record = $dbh->getRecord();
		$record->interval_start_at = $report->interval_start_at;
		$record->interval_end_at = $report->interval_end_at;
		$record->type = Constants::REPORT_TYPE_ADHOC;
		$record->unique_id = ( new Uuid() )->V4();
		$record->content = \function_exists( 'gzdeflate' ) ?
			\gzdeflate( $response->action_response_data[ 'render_output' ] )
			: $response->action_response_data[ 'render_output' ];
		$dbh->getQueryInserter()->insert( $record );
		return $record->unique_id;
	}

	/**
	 * @return BaseBuilder[]
	 */
	public function getComponentBuilders( string $type ) :array {
		return \array_map(
			function ( $builder ) {
				return new $builder();
			},
			\array_filter(
				Constants::COMPONENT_REPORT_BUILDERS,
				function ( $builder ) use ( $type ) {
					/** @var BaseBuilder $builder */
					return $builder::TYPE === $type;
				}
			)
		);
	}
}