<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Standing\{
	ResolveStanding,
	VisitorStanding
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Services;

/**
 * An IP's visitor standing from its IP rules and stored evidence.
 */
class ResolveStandingTest extends ShieldIntegrationTestCase {

	private array $optionSnapshot = [];

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'ip_rules' );
		$this->requireDb( 'ips' );
		$this->requireDb( 'bot_signals' );
		$this->optionSnapshot = $this->snapshotSelectedOptions( [
			'transgression_limit',
			'cs_block',
			'antibot_high_reputation_minimum',
		] );
		$opts = $this->requireController()->opts;
		$opts->optSet( 'transgression_limit', 10 );
		$opts->optSet( 'cs_block', 'block_with_unblock' );
		$opts->optSet( 'antibot_high_reputation_minimum', 200 );
	}

	public function tear_down() {
		$this->restoreSelectedOptions( $this->optionSnapshot );
		parent::tear_down();
	}

	public function test_ip_without_rules_is_normal() :void {
		$standing = $this->resolve( '10.0.30.1' );
		$this->assertSame( VisitorStanding::NORMAL, $standing->level );
		$this->assertSame( 0, $standing->evidence );
	}

	public function test_offense_evidence_makes_an_ip_doubtful_then_hostile() :void {
		$this->autoBlockWithEvidence( '10.0.30.2', 399 );
		$this->autoBlockWithEvidence( '10.0.30.3', 400 );
		$this->autoBlockWithEvidence( '10.0.30.4', 1000 );

		$this->assertSame( VisitorStanding::NORMAL, $this->resolve( '10.0.30.2' )->level );
		$this->assertStanding( VisitorStanding::DOUBTFUL, [ VisitorStanding::REASON_OFFENSES ], $this->resolve( '10.0.30.3' ) );
		$this->assertStanding( VisitorStanding::HOSTILE, [ VisitorStanding::REASON_OFFENSES ], $this->resolve( '10.0.30.4' ) );
	}

	public function test_hostile_hold_keeps_an_ip_hostile_after_its_evidence_fades() :void {
		$now = Services::Request()->ts();
		$this->autoBlockWithEvidence( '10.0.30.5', 100, [ 'hostile_until' => $now + 600 ] );
		$this->autoBlockWithEvidence( '10.0.30.6', 100, [ 'hostile_until' => $now - 1 ] );

		$held = $this->resolve( '10.0.30.5' );
		$this->assertStanding( VisitorStanding::HOSTILE, [ VisitorStanding::REASON_HOSTILE_HOLD ], $held );
		$this->assertSame( $now + 600, $held->hostileUntil );
		$this->assertSame( VisitorStanding::NORMAL, $this->resolve( '10.0.30.6' )->level, 'An expired hold no longer applies' );
	}

	public function test_offense_limit_of_zero_turns_off_offense_based_standing() :void {
		$this->requireController()->opts->optSet( 'transgression_limit', 0 );
		$this->autoBlockWithEvidence( '10.0.30.7', 5000 );

		$this->assertSame( VisitorStanding::NORMAL, $this->resolve( '10.0.30.7' )->level );
	}

	public function test_bypass_list_is_trusted_and_manual_block_is_blocked_whatever_the_evidence() :void {
		TestDataFactory::insertBypass( '10.0.30.8' );
		$this->autoBlockWithEvidence( '10.0.30.9', 5000 );
		TestDataFactory::insertManualBlock( '10.0.30.9' );

		$this->assertStanding( VisitorStanding::TRUSTED, [ VisitorStanding::REASON_BYPASS ], $this->resolve( '10.0.30.8' ) );
		$this->assertStanding( VisitorStanding::BLOCKED, [ VisitorStanding::REASON_MANUAL_BLOCK ], $this->resolve( '10.0.30.9' ) );
	}

	public function test_crowdsec_listing_is_hostile_unless_crowdsec_is_disabled() :void {
		TestDataFactory::insertCrowdsecBlock( '10.0.30.10' );

		$this->assertStanding( VisitorStanding::HOSTILE, [ VisitorStanding::REASON_CROWDSEC ], $this->resolve( '10.0.30.10' ) );

		$this->requireController()->opts->optSet( 'cs_block', 'disabled' );
		$this->assertSame( VisitorStanding::NORMAL, $this->resolve( '10.0.30.10' )->level );
	}

	public function test_unblocked_crowdsec_listing_is_doubtful_and_hostile_again_at_half_the_offense_limit() :void {
		$now = Services::Request()->ts();
		TestDataFactory::insertCrowdsecBlock( '10.0.30.11', [ 'blocked_at' => $now - 60, 'unblocked_at' => $now - 30 ] );
		TestDataFactory::insertCrowdsecBlock( '10.0.30.12', [ 'blocked_at' => $now - 60, 'unblocked_at' => $now - 30 ] );
		$this->autoBlockWithEvidence( '10.0.30.12', 500 );

		$this->assertStanding( VisitorStanding::DOUBTFUL, [ VisitorStanding::REASON_CROWDSEC_UNBLOCKED ], $this->resolve( '10.0.30.11' ) );
		$this->assertSame( VisitorStanding::HOSTILE, $this->resolve( '10.0.30.12' )->level );
	}

	public function test_high_reputation_caps_offense_based_standing_but_not_a_crowdsec_listing() :void {
		$now = Services::Request()->ts();
		foreach ( [ '10.0.30.13', '10.0.30.14' ] as $ip ) {
			TestDataFactory::insertBotSignal( $ip, [
				'auth_at'   => $now - 60,
				'altcha_at' => $now - 45,
				'notbot_at' => $now - 30,
			] );
		}
		$this->autoBlockWithEvidence( '10.0.30.13', 1000 );
		TestDataFactory::insertCrowdsecBlock( '10.0.30.14' );

		$this->assertStanding(
			VisitorStanding::DOUBTFUL,
			[ VisitorStanding::REASON_OFFENSES, VisitorStanding::REASON_HIGH_REPUTATION ],
			$this->resolve( '10.0.30.13' )
		);
		$this->assertSame( VisitorStanding::HOSTILE, $this->resolve( '10.0.30.14' )->level );
	}

	private function autoBlockWithEvidence( string $ip, int $normalEvidence, array $columns = [] ) :void {
		$id = TestDataFactory::insertAutoBlock( $ip, [ 'blocked_at' => 0 ] );
		$this->requireController()->db_con->ip_rules->getQueryUpdater()->updateById( $id, \array_merge( [
			'evidence_normal' => $normalEvidence,
			'evidence_at'     => Services::Request()->ts(),
		], $columns ) );
	}

	private function resolve( string $ip ) :VisitorStanding {
		$this->resetIpCaches();
		return ( new ResolveStanding() )->setIP( $ip )->resolve();
	}

	private function assertStanding( string $level, array $reasons, VisitorStanding $standing ) :void {
		$this->assertSame( $level, $standing->level );
		$this->assertSame( $reasons, $standing->reasons );
	}
}
