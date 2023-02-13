<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports\Components\BaseBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Crons\PluginCronsConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Options;

class ReportingController extends ExecOnceModConsumer {

	use PluginCronsConsumer;

	public const MOD = ModCon::SLUG;

	protected function canRun() :bool {
		/** @var Options $opts */
		$opts = $this->getOptions();
		return $opts->getReportFrequencyInfo() !== 'disabled' || $opts->getReportFrequencyAlert() !== 'disabled';
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
		return array_map(
			function ( $builder ) {
				/** @var BaseBuilder $builder */
				$builder = new $builder();
				return $builder->setMod( $this->getCon()->getModule_Plugin() );
			},
			array_filter(
				Constants::COMPONENT_REPORT_BUILDERS,
				function ( $builder ) use ( $type ) {
					/** @var BaseBuilder $builder */
					return $builder::TYPE === $type;
				}
			)
		);
	}
}