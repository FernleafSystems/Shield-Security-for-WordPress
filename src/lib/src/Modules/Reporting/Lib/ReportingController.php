<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports\Components\BaseBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Crons\PluginCronsConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules;

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
		( new ReportGenerator() )
			->setMod( $this->getMod() )
			->auto();
	}

	/**
	 * @return BaseBuilder[]
	 */
	public function getComponentBuilders( string $type ) :array {
		return array_map(
			function ( $builder ) {
				/** @var BaseBuilder $builder */
				$builder = new $builder();
				return $builder->setMod( $this->getCon()->getModule_Insights() );
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