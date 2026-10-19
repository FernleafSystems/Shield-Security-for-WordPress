<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Rest\ShieldCentral;

use Brain\Monkey\Functions;
use Carbon\Carbon;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\Event\Ops\Handler as EventHandler;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\Event\Ops\Select as EventSelect;
use FernleafSystems\Wordpress\Plugin\Shield\Rest\ShieldCentral\Support\BuildDailyTotals;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginControllerInstaller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\UnitTestControllerFactory;

class DailyTotalsBuilderTest extends BaseUnitTest {

	private const TIMEZONE = 'America/New_York';

	/** @var EventSelect&\PHPUnit\Framework\MockObject\MockObject */
	private EventSelect $selector;

	/** @var list<array{events:string[], starts:int[], end:int}> */
	private array $queries = [];

	protected function setUp() :void {
		parent::setUp();
		Functions\when( 'wp_timezone' )->justReturn( new \DateTimeZone( self::TIMEZONE ) );
		$this->selector = $this->createMock( EventSelect::class );
		$this->selector->method( 'sumEventsPerWindow' )->willReturnCallback(
			function ( array $events, array $starts, int $end ) :array {
				$this->queries[] = [ 'events' => $events, 'starts' => $starts, 'end' => $end ];
				return [];
			}
		);
		$handler = $this->createMock( EventHandler::class );
		$handler->method( 'getQuerySelector' )->willReturn( $this->selector );
		UnitTestControllerFactory::install( null, null, (object)[ 'db_con' => (object)[ 'events' => $handler ] ] );
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_full_days_in_the_sites_time_zone_end_yesterday_and_a_summer_time_day_is_25_hours() :void {
		// 03:00 UTC on 3 Nov is still 2 Nov in New York, so yesterday there is 1 Nov, the day the clocks go back.
		$totals = ( new BuildDailyTotals() )->build( '2026-10-31', Carbon::create( 2026, 11, 3, 3, 0, 0, 'UTC' ) );

		$this->assertSame( [ '2026-10-31', '2026-11-01' ], [ $totals[ 'from' ], $totals[ 'through' ] ] );
		$query = $this->queries[ 0 ];
		$this->assertSame( BuildDailyTotals::EVENTS, $query[ 'events' ] );
		$this->assertSame( [
			Carbon::create( 2026, 10, 31, 0, 0, 0, self::TIMEZONE )->timestamp,
			Carbon::create( 2026, 11, 1, 0, 0, 0, self::TIMEZONE )->timestamp,
		], $query[ 'starts' ] );
		$this->assertSame( 25*3600, $query[ 'end' ] - $query[ 'starts' ][ 1 ] + 1 );
	}

	public function test_the_day_asked_from_is_kept_between_400_days_ago_and_yesterday_and_defaults_to_365(): void {
		$now = Carbon::create( 2026, 10, 19, 12, 0, 0, self::TIMEZONE );
		$builder = new BuildDailyTotals();

		foreach ( [
			'2020-01-01' => '2025-09-14',
			'2026-12-25' => '2026-10-18',
			'2026-02-31' => '2025-10-19',
			'not a date' => '2025-10-19',
			''           => '2025-10-19',
		] as $since => $expected ) {
			$this->assertSame( $expected, $builder->build( $since, $now )[ 'from' ], 'since '.$since );
		}
		$this->assertSame( '2025-10-19', $builder->build( null, $now )[ 'from' ] );
	}

	public function test_from_is_the_oldest_day_held_when_that_is_later_than_the_day_asked_from(): void {
		$this->selector->method( 'getOldestCreatedAt' )->willReturn( Carbon::create( 2026, 10, 10, 23, 30, 0, self::TIMEZONE )->timestamp );

		$totals = ( new BuildDailyTotals() )->build( '2026-09-01', Carbon::create( 2026, 10, 19, 12, 0, 0, self::TIMEZONE ) );

		$this->assertSame( [ '2026-10-10', '2026-10-18' ], [ $totals[ 'from' ], $totals[ 'through' ] ] );
		// The query still covers every day asked for: the oldest row only moves the answer's from.
		$this->assertSame( Carbon::create( 2026, 9, 1, 0, 0, 0, self::TIMEZONE )->timestamp, $this->queries[ 0 ][ 'starts' ][ 0 ] );
	}

	public function test_a_zone_whose_clocks_jump_at_midnight_still_has_every_day_through_yesterday(): void {
		Functions\when( 'wp_timezone' )->justReturn( new \DateTimeZone( 'America/Santiago' ) );

		// Santiago's clocks go forward at midnight on 6 Sep 2026, so that day starts at 01:00.
		$totals = ( new BuildDailyTotals() )->build( '2026-09-04', Carbon::create( 2026, 9, 8, 12, 0, 0, 'UTC' ) );

		$this->assertSame( '2026-09-07', $totals[ 'through' ] );
		$this->assertSame( [
			Carbon::create( 2026, 9, 4, 0, 0, 0, 'America/Santiago' )->timestamp,
			Carbon::create( 2026, 9, 5, 0, 0, 0, 'America/Santiago' )->timestamp,
			Carbon::create( 2026, 9, 6, 1, 0, 0, 'America/Santiago' )->timestamp,
			Carbon::create( 2026, 9, 7, 0, 0, 0, 'America/Santiago' )->timestamp,
		], $this->queries[ 0 ][ 'starts' ] );
	}

	public function test_counts_or_an_oldest_day_that_could_not_be_read_send_no_totals(): void {
		$unreadableCounts = $this->createMock( EventSelect::class );
		$unreadableCounts->method( 'sumEventsPerWindow' )->willThrowException( new \RuntimeException( 'failed' ) );
		$unreadableOldest = $this->createMock( EventSelect::class );
		$unreadableOldest->method( 'sumEventsPerWindow' )->willReturn( [] );
		$unreadableOldest->method( 'getOldestCreatedAt' )->willThrowException( new \RuntimeException( 'failed' ) );

		foreach ( [ $unreadableCounts, $unreadableOldest ] as $selector ) {
			$handler = $this->createMock( EventHandler::class );
			$handler->method( 'getQuerySelector' )->willReturn( $selector );
			UnitTestControllerFactory::install( null, null, (object)[ 'db_con' => (object)[ 'events' => $handler ] ] );

			$this->assertNull( ( new BuildDailyTotals() )->build( '2026-10-16', Carbon::create( 2026, 10, 19, 12, 0, 0, self::TIMEZONE ) ) );
		}
	}

	public function test_only_counts_above_zero_are_sent_by_their_day(): void {
		$selector = $this->createMock( EventSelect::class );
		$selector->method( 'sumEventsPerWindow' )->willReturn( [
			2 => [ 'firewall_block' => 4, 'conn_kill' => 0 ],
			0 => [ 'user_login' => 1 ],
			1 => [ 'bottrack_404' => 0 ],
		] );
		$handler = $this->createMock( EventHandler::class );
		$handler->method( 'getQuerySelector' )->willReturn( $selector );
		UnitTestControllerFactory::install( null, null, (object)[ 'db_con' => (object)[ 'events' => $handler ] ] );

		$totals = ( new BuildDailyTotals() )->build( '2026-10-16', Carbon::create( 2026, 10, 19, 12, 0, 0, self::TIMEZONE ) );

		$this->assertSame( [
			'2026-10-16' => [ 'user_login' => 1 ],
			'2026-10-18' => [ 'firewall_block' => 4 ],
		], $totals[ 'days' ] );
	}
}