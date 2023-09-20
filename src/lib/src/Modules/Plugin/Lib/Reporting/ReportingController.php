<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\FullPageDisplay\DisplayReport;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports\Components\BaseBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Crons\PluginCronsConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\DB\Reports\Ops\Record;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\ConvertHtmlToPDF;

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

	/**
	 * @throws \Exception
	 */
	public function convertToPdf( int $reportID ) :string {
		/** @var Record $report */
		$report = $this->mod()
					   ->getDbH_Reports()
					   ->getQuerySelector()
					   ->byId( $reportID );
		if ( empty( $report ) ) {
			throw new \Exception( 'Invalid report' );
		}
		return ( new ConvertHtmlToPDF() )->run( \gzinflate( $report->content ) );
	}

	public function getReportURL( string $uniqueReportID ) :string {
		return self::con()->plugin_urls->noncedPluginAction( DisplayReport::class, null, [
			'report_unique_id' => $uniqueReportID,
		] );
	}

	public function getReportTypeName( string $type ) :string {
		return [
				   Constants::REPORT_TYPE_ALERT  => __( 'Alert', 'wp-simple-firewall' ),
				   Constants::REPORT_TYPE_INFO   => __( 'Info', 'wp-simple-firewall' ),
				   Constants::REPORT_TYPE_CUSTOM => __( 'Custom', 'wp-simple-firewall' ),
			   ][ $type ] ?? 'invalid report type';
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

	public function getReportAreas( bool $slugsOnly = false ) :array {
		$areas = [
			Constants::REPORT_AREA_CHANGES => \array_filter( \array_map(
				function ( $auditor ) {
					try {
						return $auditor->getReporter()->getZoneName();
					}
					catch ( \Exception $e ) {
						return null;
					}
				},
				self::con()->getModule_AuditTrail()->getAuditCon()->getAuditors()
			) ),
			Constants::REPORT_AREA_STATS   => [
				'security'      => __( 'Security' ),
				'wordpress'     => __( 'WordPress' ),
				'user_accounts' => __( 'User Accounts', 'wp-simple-firewall' ),
				'user_access'   => __( 'User Access', 'wp-simple-firewall' ),
			],
			Constants::REPORT_AREA_SCANS   => [
				'scan_results_new'     => __( 'New Results' ),
				'scan_results_current' => __( 'Current Summary' ),
				'scan_repairs'         => __( 'Scan File Repairs' ),
			],
		];

		return $slugsOnly ?
			\array_map( function ( array $area ) {
				return \array_keys( $area );
			}, $areas )
			: $areas;
	}
}