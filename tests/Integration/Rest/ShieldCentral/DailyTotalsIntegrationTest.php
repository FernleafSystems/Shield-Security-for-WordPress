<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Rest\ShieldCentral;

use Carbon\Carbon;
use FernleafSystems\Wordpress\Plugin\Shield\Rest\ShieldCentral\Support\BuildDailyTotals;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Services;

/**
 * The daily totals sent to ShieldCentral, summed from the events table by the site's own days. The days are in 2031,
 * so no other test's rows fall inside them.
 */
class DailyTotalsIntegrationTest extends ShieldIntegrationTestCase {

	private string $timezoneSnapshot = '';

	/** @var mixed */
	private $gmtOffsetSnapshot;

	/** @var int[] */
	private array $insertedIds = [];

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'events' );
		$this->timezoneSnapshot = (string)\get_option( 'timezone_string', '' );
		$this->gmtOffsetSnapshot = \get_option( 'gmt_offset', 0 );
		\update_option( 'timezone_string', 'America/New_York' );
		\update_option( 'gmt_offset', 0 );
	}

	public function tear_down() {
		if ( static::con() !== null ) {
			if ( !empty( $this->insertedIds ) ) {
				$wpdb = Services::WpDb()->loadWpdb();
				$table = self::con()->db_con->events->getTableSchema()->table;
				$wpdb->query( "DELETE FROM `{$table}` WHERE `id` IN (".\implode( ',', $this->insertedIds ).')' );
			}
			\update_option( 'timezone_string', $this->timezoneSnapshot );
			\update_option( 'gmt_offset', $this->gmtOffsetSnapshot );
		}
		parent::tear_down();
	}

	public function test_counts_are_summed_by_the_sites_days_across_the_summer_time_change(): void {
		// The clocks go back at 02:00 on 2 Nov 2031 in New York, so 1:30 comes twice and the day is 25 hours long.
		foreach ( [
			[ 'firewall_block', 2, Carbon::create( 2031, 11, 2, 5, 30, 0, 'UTC' ) ],
			[ 'firewall_block', 3, Carbon::create( 2031, 11, 2, 6, 30, 0, 'UTC' ) ],
			[ 'firewall_block', 4, Carbon::create( 2031, 11, 3, 4, 30, 0, 'UTC' ) ],
			[ 'firewall_block', 7, Carbon::create( 2031, 11, 3, 5, 30, 0, 'UTC' ) ],
			[ 'conn_kill', 1, Carbon::create( 2031, 11, 1, 12, 0, 0, 'UTC' ) ],
			[ 'ip_offense', 9, Carbon::create( 2031, 11, 2, 12, 0, 0, 'UTC' ) ],
			[ 'user_login', 5, Carbon::create( 2031, 11, 4, 12, 0, 0, 'UTC' ) ],
		] as [ $event, $count, $at ] ) {
			$this->insertEvent( $event, $count, $at->timestamp );
		}

		$totals = ( new BuildDailyTotals() )->build( '2031-11-01', Carbon::create( 2031, 11, 4, 12, 0, 0, 'America/New_York' ) );

		$this->assertSame( '2031-11-03', $totals[ 'through' ] );
		$this->assertSame( [
			'2031-11-01' => [ 'conn_kill' => 1 ],
			'2031-11-02' => [ 'firewall_block' => 9 ],
			'2031-11-03' => [ 'firewall_block' => 7 ],
		], $totals[ 'days' ] );
	}

	public function test_a_single_day_asked_for_comes_back(): void {
		$this->insertEvent( 'bottrack_404', 6, Carbon::create( 2031, 11, 3, 15, 0, 0, 'UTC' )->timestamp );

		$totals = ( new BuildDailyTotals() )->build( '2031-11-03', Carbon::create( 2031, 11, 4, 12, 0, 0, 'America/New_York' ) );

		$this->assertSame( [ '2031-11-03' => [ 'bottrack_404' => 6 ] ], $totals[ 'days' ] );
	}

	private function insertEvent( string $event, int $count, int $createdAt ) :void {
		$dbh = self::con()->db_con->events;
		$record = $dbh->getRecord();
		$record->event = $event;
		$record->count = $count;
		$record->created_at = $createdAt;
		$dbh->getQueryInserter()->insert( $record );
		$this->insertedIds[] = (int)Services::WpDb()->loadWpdb()->insert_id;
	}
}