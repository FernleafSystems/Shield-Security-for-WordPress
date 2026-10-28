<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Updates\HandleUpgrade;
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
		$ledger = $this->ledger( [ 'bottrack_loginfailed' => 'normal', 'bottrack_fakewebcrawler' => 'strong', 'bottrack_404' => 'weak' ] );

		( new ProcessOffense() )->setIP( $ip )->incrementOffenses( 3, $ledger );

		$this->resetIpCaches();
		$record = ( new IpRuleStatus( $ip ) )->getRuleForAutoBlock();
		$this->assertNotNull( $record );
		$this->assertSame( 100, $record->evidence_normal );
		$this->assertSame( 300, $record->evidence_strong );
		$this->assertSame( [ 'bottrack_404' => 50 ], $record->meta[ StoredEvidence::META_WEAK ] ?? null );
		$this->assertSame( Services::Request()->ts(), $record->evidence_at );
	}

	public function test_stored_evidence_fades_before_new_evidence_is_added() :void {
		$now = Services::Request()->ts();
		$id = TestDataFactory::insertAutoBlock( '203.0.113.72', [ 'blocked_at' => 0 ] );
		$this->setColumns( $id, [
			'evidence_normal' => 400,
			'evidence_strong' => 300,
			'evidence_at'     => $now - \DAY_IN_SECONDS,
		] );

		( new RecordEvidence() )->toRecord( $this->record( $id ), $this->ledger( [ 'bottrack_loginfailed' => 'normal' ] ) );

		$record = $this->record( $id );
		$this->assertSame( 200 + 100, $record->evidence_normal );
		$this->assertSame( (int)\floor( 300*\pow( 0.5, 1/7 ) ), $record->evidence_strong );
		$this->assertSame( $now, $record->evidence_at );
	}

	public function test_each_weak_signal_is_capped_however_often_it_repeats() :void {
		$id = TestDataFactory::insertAutoBlock( '203.0.113.73', [ 'blocked_at' => 0 ] );

		for ( $i = 0 ; $i < 6 ; $i++ ) {
			( new RecordEvidence() )->toRecord( $this->record( $id ), $this->ledger( [ 'bottrack_404' => 'weak' ] ) );
		}
		( new RecordEvidence() )->toRecord( $this->record( $id ), $this->ledger( [ 'cooldown_fail' => 'weak' ] ) );

		$this->assertSame(
			[ 'bottrack_404' => EvidenceWeights::WEAK_SIGNAL_CAP, 'cooldown_fail' => 50 ],
			$this->record( $id )->meta[ StoredEvidence::META_WEAK ] ?? null
		);
	}

	public function test_reaching_the_offense_limit_holds_the_ip_as_hostile_for_the_auto_block_expiry() :void {
		$this->requireController()->opts->optSet( 'transgression_limit', 3 );
		$below = TestDataFactory::insertAutoBlock( '203.0.113.74', [ 'blocked_at' => 0 ] );
		$reached = TestDataFactory::insertAutoBlock( '203.0.113.75', [ 'blocked_at' => 0 ] );

		( new RecordEvidence() )->toRecord( $this->record( $below ), $this->ledger( [ 'bottrack_loginfailed' => 'normal', 'bottrack_404' => 'weak' ] ) );
		( new RecordEvidence() )->toRecord( $this->record( $reached ), $this->ledger( [ 'bottrack_fakewebcrawler' => 'strong' ] ) );

		$this->assertSame( 0, $this->record( $below )->hostile_until );
		$this->assertSame( Services::Request()->ts() + \HOUR_IN_SECONDS, $this->record( $reached )->hostile_until );
	}

	public function test_an_offense_limit_of_zero_never_holds_the_ip_as_hostile() :void {
		$this->requireController()->opts->optSet( 'transgression_limit', 0 );
		$id = TestDataFactory::insertAutoBlock( '203.0.113.76', [ 'blocked_at' => 0 ] );

		( new RecordEvidence() )->toRecord( $this->record( $id ), $this->ledger( [ 'bottrack_fakewebcrawler' => 'strong' ] ) );

		$this->assertSame( 300, $this->record( $id )->evidence_strong );
		$this->assertSame( 0, $this->record( $id )->hostile_until );
	}

	public function test_upgrade_seeds_evidence_only_for_auto_block_records_without_any() :void {
		$lastAccess = Services::Request()->ts() - 600;
		$blocked = TestDataFactory::insertAutoBlock( '203.0.113.77', [
			'offenses'       => 10,
			'blocked_at'     => $lastAccess - 60,
			'last_access_at' => $lastAccess,
		] );
		$offended = TestDataFactory::insertAutoBlock( '203.0.113.78', [
			'offenses'       => 4,
			'blocked_at'     => 0,
			'last_access_at' => $lastAccess,
		] );
		$alreadySeeded = TestDataFactory::insertAutoBlock( '203.0.113.79', [ 'offenses' => 4, 'blocked_at' => 0 ] );
		$this->setColumns( $alreadySeeded, [ 'evidence_normal' => 50, 'evidence_at' => $lastAccess ] );
		$manual = TestDataFactory::insertManualBlock( '203.0.113.80', [ 'offenses' => 4 ] );

		$seed = new \ReflectionMethod( HandleUpgrade::class, 'seedIpStandingEvidence' );
		$seed->setAccessible( true );
		$seed->invoke( new HandleUpgrade() );

		$this->assertSame( [ 1000, $lastAccess, $lastAccess + \HOUR_IN_SECONDS ], $this->evidenceColumns( $blocked ) );
		$this->assertSame( [ 400, $lastAccess, 0 ], $this->evidenceColumns( $offended ) );
		$this->assertSame( [ 50, $lastAccess, 0 ], $this->evidenceColumns( $alreadySeeded ) );
		$this->assertSame( [ 0, 0, 0 ], $this->evidenceColumns( $manual ) );
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

	private function record( int $id ) :IpRulesDB\Record {
		/** @var IpRulesDB\Record $record */
		$record = $this->requireController()->db_con->ip_rules->getQuerySelector()->byId( $id );
		return $record;
	}

	private function evidenceColumns( int $id ) :array {
		$record = $this->record( $id );
		return [ $record->evidence_normal, $record->evidence_at, $record->hostile_until ];
	}

	private function setColumns( int $id, array $columns ) :void {
		$this->requireController()->db_con->ip_rules->getQueryUpdater()->updateById( $id, $columns );
	}
}
