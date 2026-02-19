<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\{
	Handler,
	Meter\MeterOverallConfig
};

trait BuiltMetersFixture {

	private function resetBuiltMetersCache() :void {
		$ref = new \ReflectionClass( Handler::class );
		$prop = $ref->getProperty( 'BuiltMeters' );
		$prop->setAccessible( true );
		$prop->setValue( null, [] );
	}

	/**
	 * @param array<int, array<string, mixed>> $components
	 */
	private function setOverallConfigMeterComponents( array $components ) :void {
		$ref = new \ReflectionClass( Handler::class );
		$prop = $ref->getProperty( 'BuiltMeters' );
		$prop->setAccessible( true );

		$meters = (array)$prop->getValue();
		$meters[ MeterOverallConfig::SLUG ] = [
			'title'       => 'Overall',
			'subtitle'    => 'Overall',
			'warning'     => [],
			'description' => [],
			'components'  => $components,
			'totals'      => [
				'score'        => 0,
				'max_weight'   => 1,
				'percentage'   => 100,
				'letter_score' => 'A',
			],
			'status'      => 'h',
			'rgbs'        => [ 16, 128, 0 ],
			'has_critical'=> false,
		];
		$prop->setValue( null, $meters );
	}
}
