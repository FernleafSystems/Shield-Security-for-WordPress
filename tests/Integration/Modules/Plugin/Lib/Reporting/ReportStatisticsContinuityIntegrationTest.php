<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Modules\Plugin\Lib\Reporting;

use Carbon\Carbon;
use FernleafSystems\Wordpress\Plugin\Shield\Events\ConsolidateAllEvents;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\{
	Constants,
	CreateReportVO,
	ReportVO,
	ResolveReportViewContracts
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Data\BuildForStats;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Services;

class ReportStatisticsContinuityIntegrationTest extends ShieldIntegrationTestCase {

	private const EVENT_PREFIX = 'reporting_continuity_';

	private string $timezoneSnapshot = '';
	private string $phpTimezoneSnapshot = '';
	private string $frequencyInfoSnapshot = '';

	/** @var mixed */
	private $gmtOffsetSnapshot;

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'events' );
		$this->requireDb( 'reports' );
		$this->timezoneSnapshot = (string)\get_option( 'timezone_string', '' );
		$this->gmtOffsetSnapshot = \get_option( 'gmt_offset', 0 );
		$this->phpTimezoneSnapshot = \date_default_timezone_get();
		$this->frequencyInfoSnapshot = (string)self::con()->opts->optGet( 'frequency_info' );
		\update_option( 'timezone_string', 'America/New_York' );
		\update_option( 'gmt_offset', 0 );
		\date_default_timezone_set( 'UTC' );
		self::con()->opts->optSet( 'frequency_info', 'weekly' );
	}

	public function tear_down() {
		if ( static::con() !== null ) {
			$wpdb = Services::WpDb()->loadWpdb();
			$table = self::con()->db_con->events->getTableSchema()->table;
			$wpdb->query( $wpdb->prepare( "DELETE FROM `{$table}` WHERE `event` LIKE %s", self::EVENT_PREFIX.'%' ) );
			\delete_option( self::con()->prefix( ConsolidateAllEvents::CURSOR_OPTION ) );
			\delete_transient( self::con()->prefix( ConsolidateAllEvents::GUARD_TRANSIENT ) );
			\update_option( 'timezone_string', $this->timezoneSnapshot );
			\update_option( 'gmt_offset', $this->gmtOffsetSnapshot );
			\date_default_timezone_set( $this->phpTimezoneSnapshot );
			self::con()->opts->optSet( 'frequency_info', $this->frequencyInfoSnapshot );
		}
		parent::tear_down();
	}

	/** @group database-compat */
	public function test_current_week_equals_following_report_previous_week_after_compaction() :void {
		$events = [
			self::EVENT_PREFIX.'blocked',
			self::EVENT_PREFIX.'killed',
		];
		$firstReport = $this->createReportAt( Carbon::create( 2026, 7, 6, 12, 0, 0, 'America/New_York' ) );
		$secondReport = $this->createReportAt( Carbon::create( 2026, 7, 13, 12, 0, 0, 'America/New_York' ) );
		$this->assertSame( $firstReport->start_at, $secondReport->previous_start_at );
		$this->assertSame( $firstReport->end_at, $secondReport->previous_end_at );

		$firstDay = Carbon::createFromTimestamp( $firstReport->start_at, 'America/New_York' );
		for ( $day = 0; $day < 7; $day++ ) {
			$dayStart = ( clone $firstDay )->addDays( $day );
			$this->insertEvent( $events[ 0 ], 10 + $day, ( clone $dayStart )->addHours( 2 )->timestamp );
			$this->insertEvent( $events[ 0 ], 20 + $day, ( clone $dayStart )->addHours( 20 )->timestamp );
			$this->insertEvent( $events[ 1 ], 3 + $day, ( clone $dayStart )->addHours( 4 )->timestamp );
			$this->insertEvent( $events[ 1 ], 7 + $day, ( clone $dayStart )->addHours( 22 )->timestamp );
		}

		$current = ( new BuildForStats( $firstReport ) )->buildForGroup( $events );
		foreach ( $events as $event ) {
			$this->assertSame( 14, $this->countEventRows( $event, $firstReport ) );
		}
		$this->assertTrue( ( new ConsolidateAllEvents() )->run(
			Carbon::create( 2026, 7, 6, 12, 0, 0, 'America/New_York' )
		) );
		$following = ( new BuildForStats( $secondReport ) )->buildForGroup( $events );

		foreach ( $events as $event ) {
			$this->assertSame( 7, $this->countEventRows( $event, $firstReport ) );
			$this->assertGreaterThan( 0, $current[ $event ][ 'count_current_period' ] );
			$this->assertSame(
				$current[ $event ][ 'count_current_period' ],
				$following[ $event ][ 'count_previous_period' ],
				$event.' must remain stable across adjacent reports and maintenance.'
			);
		}
	}

	public function test_statistics_period_contract_uses_same_query_boundaries_and_site_dates() :void {
		$report = $this->createReportAt( Carbon::create( 2026, 7, 6, 12, 0, 0, 'America/New_York' ) );
		$periods = ( new ResolveReportViewContracts() )->statisticsPeriods( $report );
		$WP = Services::WpGeneral();

		$this->assertSame( $WP->getTimeStringForDisplay( $report->start_at, false ), $periods[ 'current' ][ 'date_start' ] );
		$this->assertSame( $WP->getTimeStringForDisplay( $report->end_at, false ), $periods[ 'current' ][ 'date_end' ] );
		$this->assertSame( $WP->getTimeStringForDisplay( $report->previous_start_at, false ), $periods[ 'previous' ][ 'date_start' ] );
		$this->assertSame( $WP->getTimeStringForDisplay( $report->previous_end_at, false ), $periods[ 'previous' ][ 'date_end' ] );
	}

	private function createReportAt( Carbon $reference ) :ReportVO {
		return ( new class( $reference ) extends CreateReportVO {
			private Carbon $reference;

			public function __construct( Carbon $reference ) {
				$this->reference = $reference;
			}

			protected function currentRequestCarbon() :Carbon {
				return clone $this->reference;
			}
		} )->create( Constants::REPORT_TYPE_INFO );
	}

	private function insertEvent( string $event, int $count, int $createdAt ) :void {
		$dbh = self::con()->db_con->events;
		$record = $dbh->getRecord();
		$record->event = $event;
		$record->count = $count;
		$record->created_at = $createdAt;
		$dbh->getQueryInserter()->insert( $record );
	}

	private function countEventRows( string $event, ReportVO $report ) :int {
		return self::con()->db_con->events->getQuerySelector()
			->filterByEvent( $event )
			->filterByBoundary( $report->start_at, $report->end_at )
			->count();
	}
}
