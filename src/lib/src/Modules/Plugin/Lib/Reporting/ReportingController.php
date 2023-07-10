<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting;

use FernleafSystems\Utilities\Logic\ExecOnce;
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