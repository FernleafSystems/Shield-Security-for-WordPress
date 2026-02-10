<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Reporting;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Data\BuildForStats;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class DiffPercentageCalculationTest extends TestCase {

	/**
	 * @dataProvider providerDiffPercentage
	 */
	public function test_diff_percentage( int $current, int $previous, int $expected ) :void {
		$this->assertSame( $expected, BuildForStats::calcDiffPercentage( $current, $previous ) );
	}

	public static function providerDiffPercentage() :array {
		return [
			'increase_50pct'         => [ 150, 100, 50 ],
			'decrease_40pct'         => [ 60, 100, -40 ],
			'no_change'              => [ 100, 100, 0 ],
			'doubled'                => [ 200, 100, 100 ],
			'prev_zero_cur_nonzero'  => [ 5, 0, 100 ],
			'both_zero'              => [ 0, 0, 0 ],
			'dropped_to_zero'        => [ 0, 50, -100 ],
			'rounding_down'          => [ 4, 3, 33 ],
			'rounding_up'            => [ 5, 3, 67 ],
			'large_numbers'          => [ 1000000, 500000, 100 ],
			'small_diff_large_base'  => [ 1001, 1000, 0 ],
			'half_percent_rounds_up' => [ 201, 200, 1 ],
		];
	}
}
