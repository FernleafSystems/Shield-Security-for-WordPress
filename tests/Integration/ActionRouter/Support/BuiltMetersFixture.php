<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\{
	Meter\MeterOverallConfig
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\MeterAnalysisBuiltMetersCacheTrait;

trait BuiltMetersFixture {

	use MeterAnalysisBuiltMetersCacheTrait;

	private function resetBuiltMetersCache() :void {
		$this->resetBuiltMetersCaches();
	}

	/**
	 * @param array<int, array<string, mixed>> $components
	 */
	private function setOverallConfigMeterComponents( array $components ) :void {
		$meters = $this->getBuiltMetersCache();
		$meters[ MeterOverallConfig::SLUG ] = \array_merge(
			$this->buildMeterFixture( 100, 'Overall', 1 ),
			[
				'components' => $components,
			]
		);
		$this->setBuiltMetersCache( $meters );
	}
}
