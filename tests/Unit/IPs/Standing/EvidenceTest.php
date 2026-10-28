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

	public function test_offense_event_adds_the_units_of_its_strength() :void {
		$ledger = new EvidenceLedger();
		$weights = new EvidenceWeights();
		$weights->addEvent( $ledger, 'bottrack_loginfailed', [], [ 'evidence_strength' => 'normal' ] );
		$weights->addEvent( $ledger, 'bottrack_fakewebcrawler', [], [ 'evidence_strength' => 'strong' ] );
		$weights->addEvent( $ledger, 'bottrack_404', [], [ 'evidence_strength' => 'weak' ] );
		$weights->addEvent( $ledger, 'bottrack_404', [], [ 'evidence_strength' => 'weak' ] );

		$this->assertSame( 100, $ledger->unitsFor( EvidenceWeights::NORMAL ) );
		$this->assertSame( 300, $ledger->unitsFor( EvidenceWeights::STRONG ) );
		$this->assertSame( [ 'bottrack_404' => 100 ], $ledger->weakSignals() );
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

		$this->assertSame( 300, $ledger->unitsFor( EvidenceWeights::STRONG ) );
		$this->assertSame( 0, $ledger->unitsFor( EvidenceWeights::NORMAL ) );
		$this->assertSame( [ 'firewall_block:aggressive' => 50 ], $ledger->weakSignals() );
	}

	public function test_firewall_block_without_a_known_category_counts_at_the_event_strength() :void {
		$ledger = new EvidenceLedger();
		( new EvidenceWeights() )->addEvent(
			$ledger,
			'firewall_block',
			[ 'audit_params' => [ 'scan' => 'custom_rule' ] ],
			[ 'evidence_strength' => 'normal' ]
		);

		$this->assertSame( 100, $ledger->unitsFor( EvidenceWeights::NORMAL ) );
	}

	public function test_custom_rule_offenses_count_as_normal_evidence_per_offense() :void {
		$ledger = new EvidenceLedger();
		$weights = new EvidenceWeights();
		$weights->addCustomRuleOffenses( $ledger, 0 );
		$this->assertTrue( $ledger->isEmpty() );

		$weights->addCustomRuleOffenses( $ledger, 3 );
		$this->assertSame( 300, $ledger->unitsFor( EvidenceWeights::NORMAL ) );
	}

	public function test_evidence_halves_once_per_half_life_of_its_strength() :void {
		$at = 1_000_000;
		$this->assertSame( 50, EvidenceWeights::Fade( 100, $at, $at + \DAY_IN_SECONDS, EvidenceWeights::NORMAL ) );
		$this->assertSame( 150, EvidenceWeights::Fade( 300, $at, $at + \WEEK_IN_SECONDS, EvidenceWeights::STRONG ) );
		$this->assertSame( 100, EvidenceWeights::Fade( 100, $at, $at - 60, EvidenceWeights::NORMAL ), 'Evidence never grows' );
		$this->assertSame( 0, EvidenceWeights::Fade( 100, 0, $at, EvidenceWeights::NORMAL ), 'Evidence never recorded counts nothing' );
	}

	public function test_stored_evidence_fades_each_strength_from_when_it_was_last_added() :void {
		$at = 1_000_000;
		$record = new Record();
		$record->evidence_normal = 400;
		$record->evidence_strong = 300;
		$record->evidence_at = $at;
		$record->meta = [ StoredEvidence::META_WEAK => [ 'bottrack_404' => 200, 'cooldown_fail' => 1 ] ];

		$stored = new StoredEvidence( $record );
		$now = $at + \DAY_IN_SECONDS;

		$this->assertSame( 200, $stored->unitsFor( EvidenceWeights::NORMAL, $now ) );
		$this->assertSame( (int)\floor( 300*\pow( 0.5, 1/7 ) ), $stored->unitsFor( EvidenceWeights::STRONG, $now ) );
		$this->assertSame( [ 'bottrack_404' => 100 ], $stored->weakSignals( $now ), 'Faded-out weak signals are dropped' );
		$this->assertSame( 200 + (int)\floor( 300*\pow( 0.5, 1/7 ) ) + 100, $stored->total( $now ) );
	}
}
