<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\{
	LoadIpRules,
	MergeAutoBlockRules
};
use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\Ops as IpRulesDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components\ProcessOffense;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Standing\{
	EvidenceLedger,
	EvidenceWeights,
	RecordEvidence,
	StoredEvidence
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Offense evidence is faded and added to an IP's auto-block record in MySQL.
 */
class StandingEvidenceTest extends ShieldIntegrationTestCase {

	private const T = 1_800_000_000;

	private array $optionSnapshot = [];

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'ip_rules' );
		$this->requireDb( 'ips' );
		$this->optionSnapshot = $this->snapshotSelectedOptions( [ 'transgression_limit', 'auto_expire' ] );
		$this->requireController()->opts->optSet( 'transgression_limit', 10 );
		$this->requireController()->opts->optSet( 'auto_expire', 'hour' );
	}

	public function tear_down() {
		$this->restoreSelectedOptions( $this->optionSnapshot );
		parent::tear_down();
	}

	public function test_offense_processing_adds_its_evidence_to_the_auto_block_record() :void {
		$ip = '10.0.0.71';
		$before = Services::Request()->ts();

		( new ProcessOffense() )->setIP( $ip )->incrementOffenses(
			3,
			$this->ledger( [ 'bottrack_loginfailed' => 'normal', 'bottrack_fakewebcrawler' => 'strong', 'bottrack_404' => 'weak' ] )
		);

		$this->resetIpCaches();
		$record = ( new IpRuleStatus( $ip ) )->getRuleForAutoBlock();
		$this->assertNotNull( $record );
		$this->assertSame( 3, $record->offenses );
		$this->assertEqualsWithDelta( 1.0, $record->normal_evidence, 1e-6 );
		$this->assertEqualsWithDelta( 3.0, $record->strong_evidence, 1e-6 );
		$this->assertEquals( [ 'bottrack_404' => 0.5 ], $record->meta[ StoredEvidence::META_WEAK ] ?? null );
		$this->assertGreaterThanOrEqual( $before, $record->evidence_at );
	}

	public function test_first_evidence_carries_over_offenses_from_before_visitor_standing_exactly_once() :void {
		$ip = '10.0.0.72';
		TestDataFactory::insertAutoBlock( $ip, [ 'offenses' => 9, 'blocked_at' => 0 ] );

		( new ProcessOffense() )->setIP( $ip )->incrementOffenses( 1, $this->ledger( [ 'bottrack_loginfailed' => 'normal' ] ) );
		( new ProcessOffense() )->setIP( $ip )->incrementOffenses( 1, $this->ledger( [ 'bottrack_loginfailed' => 'normal' ] ) );

		$this->resetIpCaches();
		$record = ( new IpRuleStatus( $ip ) )->getRuleForAutoBlock();
		$this->assertSame( 11, $record->offenses );
		$this->assertEqualsWithDelta( 11.0, $record->normal_evidence, 0.01, '9 earlier offenses, plus 1 for each request' );
	}

	public function test_stored_evidence_fades_before_new_evidence_is_added() :void {
		$id = $this->autoBlock( '203.0.113.72', [
			'normal_evidence' => 4.0,
			'strong_evidence' => 3.0,
			'evidence_at'     => self::T - \DAY_IN_SECONDS,
		] );

		$this->record( $id, [ 'bottrack_loginfailed' => 'normal' ], self::T );

		$record = $this->load( $id );
		$this->assertEqualsWithDelta( 3.0, $record->normal_evidence, 1e-9 );
		$this->assertEqualsWithDelta( 3.0*\pow( 0.5, 1/7 ), $record->strong_evidence, 1e-9 );
		$this->assertSame( self::T, $record->evidence_at );
	}

	public function test_fading_does_not_depend_on_how_often_evidence_is_written() :void {
		$id = $this->autoBlock( '203.0.113.73', [ 'strong_evidence' => 3.0, 'evidence_at' => self::T ] );

		for ( $step = 1 ; $step <= 60 ; $step++ ) {
			$this->record( $id, [ 'bottrack_loginfailed' => 'normal' ], self::T + $step*300 );
		}

		$this->assertEqualsWithDelta(
			3.0*\pow( 0.5, 60*300/\WEEK_IN_SECONDS ),
			$this->load( $id )->strong_evidence,
			1e-9
		);
	}

	public function test_a_slower_request_writing_after_a_newer_one_keeps_both_contributions() :void {
		$id = $this->autoBlock( '203.0.113.74', [] );

		$this->assertTrue( $this->record( $id, [ 'bottrack_loginfailed' => 'normal' ], self::T + 1 ) );
		$this->assertTrue( $this->record( $id, [ 'bottrack_loginfailed' => 'normal' ], self::T ) );

		$record = $this->load( $id );
		$this->assertEqualsWithDelta( 2.0, $record->normal_evidence, 1e-9 );
		$this->assertSame( self::T + 1, $record->evidence_at, 'evidence_at never moves back' );
	}

	public function test_offenses_and_evidence_from_parallel_requests_are_all_added() :void {
		$id = $this->autoBlock( '203.0.113.82', [] );
		$staleA = $this->load( $id );
		$staleB = $this->load( $id );

		( new RecordEvidence() )->toRecord( $staleA, 1, $this->ledger( [ 'bottrack_loginfailed' => 'normal' ] ), self::T );
		( new RecordEvidence() )->toRecord( $staleB, 1, $this->ledger( [ 'bottrack_loginfailed' => 'normal' ] ), self::T );

		$record = $this->load( $id );
		$this->assertSame( 2, $record->offenses );
		$this->assertEqualsWithDelta( 2.0, $record->normal_evidence, 1e-9 );
	}

	public function test_each_weak_signal_is_capped_however_often_it_repeats() :void {
		$id = $this->autoBlock( '203.0.113.75', [] );

		for ( $i = 0 ; $i < 6 ; $i++ ) {
			$this->record( $id, [ 'bottrack_404' => 'weak' ], self::T + $i );
		}
		$this->record( $id, [ 'cooldown_fail' => 'weak' ], self::T + 6 );

		$weak = $this->weak( $id );
		$this->assertEqualsWithDelta( EvidenceWeights::WEAK_SIGNAL_CAP, $weak[ 'bottrack_404' ] ?? 0, 0.01 );
		$this->assertEqualsWithDelta( 0.5, $weak[ 'cooldown_fail' ] ?? 0, 0.01 );
	}

	public function test_reaching_the_offense_limit_holds_the_ip_as_hostile_for_the_auto_block_expiry() :void {
		$this->requireController()->opts->optSet( 'transgression_limit', 3 );
		$below = $this->autoBlock( '203.0.113.76', [] );
		$reached = $this->autoBlock( '203.0.113.77', [] );

		$this->record( $below, [ 'bottrack_loginfailed' => 'normal', 'bottrack_404' => 'weak' ], self::T );
		$this->record( $reached, [ 'bottrack_fakewebcrawler' => 'strong' ], self::T );

		$this->assertSame( 0, $this->load( $below )->hostile_until );
		$this->assertSame( self::T + \HOUR_IN_SECONDS, $this->load( $reached )->hostile_until );
	}

	public function test_new_evidence_renews_an_active_hold_even_below_the_limit() :void {
		$held = $this->autoBlock( '203.0.113.78', [ 'hostile_until' => self::T + 60, 'evidence_at' => self::T - 60 ] );
		$expired = $this->autoBlock( '203.0.113.79', [ 'hostile_until' => self::T - 1, 'evidence_at' => self::T - 60 ] );

		$this->record( $held, [ 'bottrack_loginfailed' => 'normal' ], self::T );
		$this->record( $expired, [ 'bottrack_loginfailed' => 'normal' ], self::T );

		$this->assertSame( self::T + \HOUR_IN_SECONDS, $this->load( $held )->hostile_until );
		$this->assertSame( self::T - 1, $this->load( $expired )->hostile_until );
	}

	public function test_first_write_keeps_the_hold_implied_for_a_blocked_record_from_before_visitor_standing() :void {
		$id = TestDataFactory::insertAutoBlock( '203.0.113.83', [
			'offenses'       => 1,
			'blocked_at'     => self::T - 60,
			'last_access_at' => self::T + 1,
		] );

		$this->record( $id, [ 'bottrack_loginfailed' => 'normal' ], self::T );

		$this->assertSame( self::T + 1 + \HOUR_IN_SECONDS, $this->load( $id )->hostile_until, 'A delayed first write does not shorten the hold' );
	}

	public function test_an_offense_limit_of_zero_never_holds_the_ip_as_hostile() :void {
		$this->requireController()->opts->optSet( 'transgression_limit', 0 );
		$id = $this->autoBlock( '203.0.113.80', [] );

		$this->record( $id, [ 'bottrack_fakewebcrawler' => 'strong' ], self::T );

		$this->assertEqualsWithDelta( 3.0, $this->load( $id )->strong_evidence, 1e-9 );
		$this->assertSame( 0, $this->load( $id )->hostile_until );
	}

	public function test_merging_duplicate_auto_block_records_keeps_all_their_evidence_and_the_latest_hold() :void {
		$ip = '203.0.113.81';
		$now = Services::Request()->ts();
		$this->autoBlock( $ip, [ 'offenses' => 2, 'strong_evidence' => 3.0, 'evidence_at' => $now ] );
		$this->autoBlock( $ip, [ 'offenses' => 2, 'strong_evidence' => 3.0, 'evidence_at' => $now, 'hostile_until' => $now + 500 ] );

		( new MergeAutoBlockRules() )->byIP( $ip );

		$this->resetIpCaches();
		$record = ( new IpRuleStatus( $ip ) )->getRuleForAutoBlock();
		$this->assertSame( 4, $record->offenses );
		$this->assertEqualsWithDelta( 6.0, $record->strong_evidence, 0.01 );
		$this->assertSame( $now + 500, $record->hostile_until );
	}

	public function test_merging_keeps_a_parallel_write_to_the_kept_record_and_counts_each_duplicate_once() :void {
		$ip = '203.0.113.84';
		$now = Services::Request()->ts();
		$this->autoBlock( $ip, [ 'offenses' => 9, 'blocked_at' => 0 ] );
		$this->autoBlock( $ip, [ 'offenses' => 1, 'normal_evidence' => 1.0, 'evidence_at' => $now ] );
		$stale = \array_values( ( new LoadIpRules() )->setIP( $ip )->select() );
		$this->assertCount( 2, $stale );

		( new RecordEvidence() )->toRecord( $stale[ 0 ], 1, $this->ledger( [ 'bottrack_loginfailed' => 'normal' ] ), $now );
		( new MergeAutoBlockRules() )->byRecords( $stale );
		( new MergeAutoBlockRules() )->byRecords( $stale );

		$this->resetIpCaches();
		$record = ( new IpRuleStatus( $ip ) )->getRuleForAutoBlock();
		$this->assertSame( 11, $record->offenses );
		$this->assertEqualsWithDelta( 11.0, $record->normal_evidence + $record->strong_evidence, 0.01 );
	}

	/**
	 * @param array<string, string> $events event => evidence strength
	 */
	private function ledger( array $events ) :EvidenceLedger {
		$ledger = new EvidenceLedger();
		foreach ( $events as $event => $strength ) {
			( new EvidenceWeights() )->addEvent( $ledger, $event, [], [ 'evidence_strength' => $strength ] );
		}
		return $ledger;
	}

	private function record( int $id, array $events, int $now ) :bool {
		return ( new RecordEvidence() )->toRecord( $this->load( $id ), 0, $this->ledger( $events ), $now );
	}

	private function autoBlock( string $ip, array $columns ) :int {
		$id = TestDataFactory::insertAutoBlock( $ip, [ 'blocked_at' => 0 ] );
		if ( !empty( $columns ) ) {
			$this->requireController()->db_con->ip_rules->getQueryUpdater()->updateById( $id, $columns );
		}
		return $id;
	}

	private function load( int $id ) :IpRulesDB\Record {
		/** @var IpRulesDB\Record $record */
		$record = $this->requireController()->db_con->ip_rules->getQuerySelector()->byId( $id );
		return $record;
	}

	private function weak( int $id ) :array {
		return (array)( $this->load( $id )->meta[ StoredEvidence::META_WEAK ] ?? [] );
	}
}
