<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\Plugin\Lib\MeterAnalysis;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\BuildMeter;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class BuildMeterZeroWeightGuardTest extends BaseUnitTest {

	public function test_empty_component_set_produces_zeroed_totals() :void {
		$meter = $this->postProcess( [
			'title'       => 'Test',
			'subtitle'    => 'Test',
			'warning'     => [],
			'description' => [],
			'components'  => [],
		] );

		$this->assertSame( 0, (int)$meter[ 'totals' ][ 'score' ] );
		$this->assertSame( 0, (int)$meter[ 'totals' ][ 'max_weight' ] );
		$this->assertSame( 0, (int)$meter[ 'totals' ][ 'percentage' ] );
		$this->assertSame( BuildMeter::STATUS_LOW, (string)$meter[ 'status' ] );
	}

	public function test_zero_weight_components_do_not_divide_by_zero() :void {
		$meter = $this->postProcess( [
			'title'       => 'Test',
			'subtitle'    => 'Test',
			'warning'     => [],
			'description' => [],
			'components'  => [
				[
					'slug'          => 'c1',
					'is_applicable' => true,
					'is_critical'   => false,
					'score'         => 0,
					'weight'        => 0,
				],
			],
		] );

		$this->assertSame( 0, (int)$meter[ 'totals' ][ 'max_weight' ] );
		$this->assertSame( 0, (int)$meter[ 'totals' ][ 'percentage' ] );
		$this->assertSame( 0, (int)$meter[ 'components' ][ 0 ][ 'score_as_percent' ] );
		$this->assertSame( 0, (int)$meter[ 'components' ][ 0 ][ 'weight_as_percent' ] );
	}

	private function postProcess( array $meter ) :array {
		$build = new class extends BuildMeter {
			public function runPostProcess( array $meter ) :array {
				return $this->postProcessMeter( $meter );
			}
		};
		return $build->runPostProcess( $meter );
	}
}

