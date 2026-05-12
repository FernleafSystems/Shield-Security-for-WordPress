<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\Plugin\Lib\Reporting\Charts;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Charts\ChartOptions;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class ChartOptionsTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'sanitize_key' )->alias(
			static fn( string $key ) :string => \strtolower( \trim( $key ) )
		);
	}

	public function test_periods_use_fixed_supported_presets() :void {
		$periods = ChartOptions::periodDefinitions();

		$this->assertSame(
			[
				ChartOptions::PERIOD_7_DAYS,
				ChartOptions::PERIOD_8_WEEKS,
				ChartOptions::PERIOD_6_MONTHS,
				ChartOptions::PERIOD_12_MONTHS,
				ChartOptions::PERIOD_YEARS,
			],
			\array_keys( $periods )
		);
		$this->assertSame( ChartOptions::PERIOD_8_WEEKS, ChartOptions::defaultPeriodKey() );
		$this->assertSame( ChartOptions::PERIOD_8_WEEKS, ChartOptions::normalizePeriodKey( 'unknown' ) );
	}

	public function test_event_normalization_filters_unknown_values_using_curated_order() :void {
		$this->assertSame(
			[
				'ip_blocked',
				'login_block',
				'block_xml',
			],
			ChartOptions::normalizeEventKeys( [
				'block_xml',
				'unknown_event',
				'login_block',
				'ip_blocked',
			] )
		);
	}
}
