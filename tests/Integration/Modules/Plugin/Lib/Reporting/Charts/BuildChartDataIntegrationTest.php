<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Modules\Plugin\Lib\Reporting\Charts;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Charts\{
	BuildChartData,
	ChartOptions,
	ChartRequestVO
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Services;

class BuildChartDataIntegrationTest extends ShieldIntegrationTestCase {

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'events' );
		$this->loginAsSecurityAdmin();
	}

	public function test_daily_period_returns_oldest_to_newest_zero_filled_series() :void {
		$now = Services::Request()->carbon();
		$this->insertEventRecord( 'login_block', 3, ( clone $now )->subDays( 6 )->startOfDay()->addHour()->timestamp );
		$this->insertEventRecord( 'login_block', 2, ( clone $now )->subDays( 2 )->startOfDay()->addHour()->timestamp );
		$this->insertEventRecord( 'ip_blocked', 4, ( clone $now )->startOfDay()->addHour()->timestamp );

		$chart = ( new BuildChartData() )->build(
			( new ChartRequestVO() )->applyFromArray( [
				'period_key' => ChartOptions::PERIOD_7_DAYS,
				'event_keys' => [ 'login_block', 'ip_blocked' ],
			] )
		);

		$this->assertSame( ChartOptions::PERIOD_7_DAYS, $chart[ 'period_key' ] );
		$this->assertSame( 7, \count( $chart[ 'labels' ] ) );
		$this->assertSame(
			[
				( clone $now )->subDays( 6 )->startOfDay()->format( 'j M Y' ),
				( clone $now )->startOfDay()->format( 'j M Y' ),
			],
			[
				$chart[ 'labels' ][ 0 ] ?? '',
				$chart[ 'labels' ][ 6 ] ?? '',
			]
		);
		$this->assertSame( [ 'ip_blocked', 'login_block' ], \array_column( $chart[ 'series' ], 'key' ) );
		$this->assertSame( [ 0, 0, 0, 0, 0, 0, 4 ], $chart[ 'series' ][ 0 ][ 'data' ] );
		$this->assertSame( [ 3, 0, 0, 0, 2, 0, 0 ], $chart[ 'series' ][ 1 ][ 'data' ] );
	}

	public function test_years_period_uses_all_available_years_and_zero_fills_gaps() :void {
		$currentYear = (int)Services::Request()->carbon()->format( 'Y' );
		$startYear = $currentYear - 3;

		$this->insertEventRecord( 'block_xml', 7, \strtotime( sprintf( '%d-01-10 12:00:00', $startYear ) ) );
		$this->insertEventRecord( 'block_xml', 2, \strtotime( sprintf( '%d-05-10 12:00:00', $startYear + 2 ) ) );

		$chart = ( new BuildChartData() )->build(
			( new ChartRequestVO() )->applyFromArray( [
				'period_key' => ChartOptions::PERIOD_YEARS,
				'event_keys' => [ 'block_xml' ],
			] )
		);

		$this->assertSame(
			\array_map(
				static fn( int $year ) :string => (string)$year,
				\range( $startYear, $currentYear )
			),
			$chart[ 'labels' ]
		);
		$this->assertSame( [ 7, 0, 2, 0 ], $chart[ 'series' ][ 0 ][ 'data' ] );
	}

	private function insertEventRecord( string $event, int $count, int $createdAt ) :void {
		$dbh = self::con()->db_con->events;
		$record = $dbh->getRecord();
		$record->event = $event;
		$record->count = $count;
		$record->created_at = $createdAt;
		$dbh->getQueryInserter()->insert( $record );
	}
}
