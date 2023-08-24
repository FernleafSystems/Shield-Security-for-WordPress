<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\FullPageDisplay\DisplayReport;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports\Components\BaseBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Crons\PluginCronsConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModConsumer;

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

	public function getReportURL( string $uniqueReportID ) :string {
		return self::con()->plugin_urls->noncedPluginAction( DisplayReport::class, null, [
			'report_unique_id' => $uniqueReportID,
		] );
	}

	public function getReportTypeName( string $type ) :string {
		return [
				   Constants::REPORT_TYPE_ALERT => __( 'Alert', 'wp-simple-firewall' ),
				   Constants::REPORT_TYPE_INFO  => __( 'Info', 'wp-simple-firewall' ),
				   Constants::REPORT_TYPE_ADHOC => __( 'Ad-Hoc', 'wp-simple-firewall' ),
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
			'changes'    => \array_filter( \array_map(
				function ( $auditor ) {
					try {
						return $auditor->getReporter()->getZoneName();
					}
					catch ( \Exception $e ) {
						return null;
					}
				},
				$this->con()->getModule_AuditTrail()->getAuditCon()->getAuditors()
			) ),
			'statistics' => [
				'security'      => __( 'Security' ),
				'wordpress'     => __( 'WordPress' ),
				'user_accounts' => __( 'User Accounts', 'wp-simple-firewall' ),
				'user_access'   => __( 'User Access', 'wp-simple-firewall' ),
			],
			'scans'      => [
				'new'     => __( 'New Results' ),
				'current' => __( 'Current Summary' ),
			],
		];

		return $slugsOnly ?
			\array_map( function ( array $area ) {
				return \array_keys( $area );
			}, $areas )
			: $areas;
	}
}