<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Handler;

trait MeterAnalysisBuiltMetersCacheTrait {

	protected function resetBuiltMetersCaches() :void {
		$this->setBuiltMetersCache( [] );
		$this->setBuiltMetersByChannelCache( [] );
	}

	/**
	 * @param array<string, array<string, mixed>> $cache
	 */
	protected function setBuiltMetersCache( array $cache ) :void {
		$this->getHandlerCacheProperty( 'BuiltMeters' )->setValue( null, $cache );
	}

	/**
	 * @param array<string, array<string, array<string, mixed>>> $cache
	 */
	protected function setBuiltMetersByChannelCache( array $cache ) :void {
		$this->getHandlerCacheProperty( 'BuiltMetersByChannel' )->setValue( null, $cache );
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	protected function getBuiltMetersCache() :array {
		return (array)$this->getHandlerCacheProperty( 'BuiltMeters' )->getValue();
	}

	/**
	 * @return array<string, array<string, array<string, mixed>>>
	 */
	protected function getBuiltMetersByChannelCache() :array {
		return (array)$this->getHandlerCacheProperty( 'BuiltMetersByChannel' )->getValue();
	}

	/**
	 * @param array<int, int>|null $rgbs
	 * @return array<string, mixed>
	 */
	protected function buildMeterFixture(
		int $percentage,
		string $title = 'Summary',
		int $maxWeight = 0,
		?array $rgbs = null
	) :array {
		return [
			'title'       => $title,
			'subtitle'    => $title,
			'warning'     => [],
			'description' => [],
			'components'  => [],
			'totals'      => [
				'score'        => 0,
				'max_weight'   => $maxWeight,
				'percentage'   => $percentage,
				'letter_score' => 'A',
			],
			'status'      => 'h',
			'rgbs'        => $rgbs ?? [ 16, 128, 0 ],
			'has_critical'=> false,
		];
	}

	private function getHandlerCacheProperty( string $property ) :\ReflectionProperty {
		$ref = new \ReflectionClass( Handler::class );
		$prop = $ref->getProperty( $property );
		$prop->setAccessible( true );
		return $prop;
	}
}

