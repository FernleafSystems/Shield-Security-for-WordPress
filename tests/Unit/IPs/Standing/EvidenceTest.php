<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\IPs\Standing;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\Ops\Record;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Standing\{
	EvidenceLedger,
	EvidenceWeights,
	StoredEvidence
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	UnitTestControllerFactory
};

class EvidenceTest extends BaseUnitTest {

	private const AT = 1_000_000;

	protected function setUp() :void {
		parent::setUp();
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		UnitTestControllerFactory::install( null, null, (object)[
			'cfg' => (object)[
				'configuration' => new class {
					public function def( string $key ) :array {
						return $key === 'firewall_evidence_strengths'
							? [ 'php_code' => 'strong', 'aggressive' => 'weak' ]
							: [];
					}
				},
			],
		] );
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_offense_event_adds_the_offenses_of_its_strength() :void {
		$ledger = new EvidenceLedger();
		$weights = new EvidenceWeights();
		$weights->addEvent( $ledger, 'bottrack_loginfailed', [], [ 'evidence_strength' => 'normal' ] );
		$weights->addEvent( $ledger, 'bottrack_fakewebcrawler', [], [ 'evidence_strength' => 'strong' ] );
		$weights->addEvent( $ledger, 'bottrack_404', [], [ 'evidence_strength' => 'weak' ] );
		$weights->addEvent( $ledger, 'bottrack_404', [], [ 'evidence_strength' => 'weak' ] );

		$this->assertSame( 1.0, $ledger->offensesFor( EvidenceWeights::NORMAL ) );
		$this->assertSame( 3.0, $ledger->offensesFor( EvidenceWeights::STRONG ) );
		$this->assertSame( [ 'bottrack_404' => 1.0 ], $ledger->weakSignals() );
	}

	public function test_event_without_a_strength_adds_nothing() :void {
		$ledger = new EvidenceLedger();
		( new EvidenceWeights() )->addEvent( $ledger, 'frontpage_load', [], [] );

		$this->assertTrue( $ledger->isEmpty() );
	}

	public function test_firewall_block_counts_at_its_category_strength_as_its_own_signal() :void {
		$ledger = new EvidenceLedger();
		$weights = new EvidenceWeights();
		$weights->addEvent( $ledger, 'firewall_block', [ 'audit_params' => [ 'scan' => 'php_code' ] ], [ 'evidence_strength' => 'normal' ] );
		$weights->addEvent( $ledger, 'firewall_block', [ 'audit_params' => [ 'scan' => 'aggressive' ] ], [ 'evidence_strength' => 'normal' ] );

		$this->assertSame( 3.0, $ledger->offensesFor( EvidenceWeights::STRONG ) );
		$this->assertSame( 0.0, $ledger->offensesFor( EvidenceWeights::NORMAL ) );
		$this->assertSame( [ 'firewall_block:aggressive' => 0.5 ], $ledger->weakSignals() );
	}

	public function test_firewall_block_without_a_known_category_counts_at_the_event_strength() :void {
		$ledger = new EvidenceLedger();
		( new EvidenceWeights() )->addEvent(
			$ledger,
			'firewall_block',
			[ 'audit_params' => [ 'scan' => 'custom_rule' ] ],
			[ 'evidence_strength' => 'normal' ]
		);

		$this->assertSame( 1.0, $ledger->offensesFor( EvidenceWeights::NORMAL ) );
	}

	public function test_custom_rule_offenses_count_as_normal_evidence_per_offense() :void {
		$ledger = new EvidenceLedger();
		$weights = new EvidenceWeights();
		$weights->addCustomRuleOffenses( $ledger, 0 );
		$this->assertTrue( $ledger->isEmpty() );

		$weights->addCustomRuleOffenses( $ledger, 3 );
		$this->assertSame( 3.0, $ledger->offensesFor( EvidenceWeights::NORMAL ) );
	}

	public function test_evidence_halves_once_per_half_life_of_its_strength() :void {
		$this->assertSame( 0.5, EvidenceWeights::Fade( 1.0, self::AT, self::AT + \DAY_IN_SECONDS, EvidenceWeights::NORMAL ) );
		$this->assertSame( 1.5, EvidenceWeights::Fade( 3.0, self::AT, self::AT + \WEEK_IN_SECONDS, EvidenceWeights::STRONG ) );
		$this->assertSame( 1.0, EvidenceWeights::Fade( 1.0, self::AT, self::AT - 60, EvidenceWeights::NORMAL ), 'Evidence never grows' );
		$this->assertSame( 0.0, EvidenceWeights::Fade( 1.0, 0, self::AT, EvidenceWeights::NORMAL ), 'Evidence never recorded counts nothing' );
	}

	public function test_fading_in_many_small_steps_matches_fading_once() :void {
		$stepped = 3.0;
		for ( $minute = 1 ; $minute <= 300 ; $minute++ ) {
			$stepped = EvidenceWeights::Fade( $stepped, self::AT + ( $minute - 1 )*60, self::AT + $minute*60, EvidenceWeights::STRONG );
		}

		$this->assertEqualsWithDelta(
			EvidenceWeights::Fade( 3.0, self::AT, self::AT + 300*60, EvidenceWeights::STRONG ),
			$stepped,
			1e-9
		);
	}

	public function test_stored_evidence_fades_each_strength_from_when_it_was_last_added() :void {
		$record = new Record();
		$record->normal_evidence = 4.0;
		$record->strong_evidence = 3.0;
		$record->evidence_at = self::AT;
		$record->hostile_until = self::AT + 600;
		$record->meta = [ StoredEvidence::META_WEAK => [ 'bottrack_404' => 2.0 ] ];

		$stored = new StoredEvidence( $record );
		$now = self::AT + \DAY_IN_SECONDS;
		$strong = 3.0*\pow( 0.5, 1/7 );

		$this->assertFalse( $stored->isLegacy() );
		$this->assertSame( 2.0, $stored->offensesFor( EvidenceWeights::NORMAL, $now ) );
		$this->assertEqualsWithDelta( $strong, $stored->offensesFor( EvidenceWeights::STRONG, $now ), 1e-9 );
		$this->assertSame( [ 'bottrack_404' => 1.0 ], $stored->weakSignals( $now ) );
		$this->assertEqualsWithDelta( 2.0 + $strong + 1.0, $stored->total( $now ), 1e-9 );
		$this->assertSame( self::AT + 600, $stored->hostileUntil( \HOUR_IN_SECONDS ) );
	}

	public function test_record_from_before_visitor_standing_counts_its_offenses_as_normal_evidence() :void {
		$record = new Record();
		$record->offenses = 8;
		$record->last_access_at = self::AT;
		$record->blocked_at = self::AT - 60;

		$stored = new StoredEvidence( $record );

		$this->assertTrue( $stored->isLegacy() );
		$this->assertSame( 4.0, $stored->offensesFor( EvidenceWeights::NORMAL, self::AT + \DAY_IN_SECONDS ) );
		$this->assertSame( 0.0, $stored->offensesFor( EvidenceWeights::STRONG, self::AT ) );
		$this->assertSame( self::AT + \HOUR_IN_SECONDS, $stored->hostileUntil( \HOUR_IN_SECONDS ), 'Blocked: held for the rest of its block' );

		$record->unblocked_at = self::AT;
		$this->assertSame( 0, $stored->hostileUntil( \HOUR_IN_SECONDS ), 'Unblocked: not held' );
	}
}
