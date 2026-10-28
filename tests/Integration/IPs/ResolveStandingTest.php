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

	private int $now;

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'ip_rules' );
		$this->requireDb( 'ips' );
		$this->requireDb( 'bot_signals' );
		$this->optionSnapshot = $this->snapshotSelectedOptions( [
			'transgression_limit',
			'cs_block',
			'antibot_high_reputation_minimum',
			'auto_expire',
		] );
		$opts = $this->requireController()->opts;
		$opts->optSet( 'transgression_limit', 10 );
		$opts->optSet( 'cs_block', 'block_with_unblock' );
		$opts->optSet( 'antibot_high_reputation_minimum', 200 );
		$opts->optSet( 'auto_expire', 'hour' );
		$this->now = Services::Request()->ts();
	}

	public function tear_down() {
		$this->restoreSelectedOptions( $this->optionSnapshot );
		parent::tear_down();
	}

	public function test_ip_without_rules_is_normal() :void {
		$standing = $this->resolve( '10.0.30.1' );
		$this->assertSame( VisitorStanding::NORMAL, $standing->level );
		$this->assertSame( 0.0, $standing->evidence );
	}

	public function test_offense_evidence_makes_an_ip_doubtful_then_hostile() :void {
		$this->autoBlockWithEvidence( '10.0.30.2', 3.99 );
		$this->autoBlockWithEvidence( '10.0.30.3', 4.0 );
		$this->autoBlockWithEvidence( '10.0.30.4', 10.0 );

		$this->assertSame( VisitorStanding::NORMAL, $this->resolve( '10.0.30.2' )->level );
		$this->assertStanding( VisitorStanding::DOUBTFUL, [ VisitorStanding::REASON_OFFENSES ], $this->resolve( '10.0.30.3' ) );
		$this->assertStanding( VisitorStanding::HOSTILE, [ VisitorStanding::REASON_OFFENSES ], $this->resolve( '10.0.30.4' ) );
	}

	public function test_hostile_hold_keeps_an_ip_hostile_after_its_evidence_fades() :void {
		$this->autoBlockWithEvidence( '10.0.30.5', 1.0, [ 'hostile_until' => $this->now + 600 ] );
		$this->autoBlockWithEvidence( '10.0.30.6', 1.0, [ 'hostile_until' => $this->now - 1 ] );

		$held = $this->resolve( '10.0.30.5' );
		$this->assertStanding( VisitorStanding::HOSTILE, [ VisitorStanding::REASON_HOSTILE_HOLD ], $held );
		$this->assertSame( $this->now + 600, $held->hostileUntil );
		$this->assertSame( VisitorStanding::NORMAL, $this->resolve( '10.0.30.6' )->level, 'An expired hold no longer applies' );
	}

	public function test_offense_limit_of_zero_turns_off_offense_based_standing() :void {
		$this->requireController()->opts->optSet( 'transgression_limit', 0 );
		$this->autoBlockWithEvidence( '10.0.30.7', 50.0 );

		$this->assertSame( VisitorStanding::NORMAL, $this->resolve( '10.0.30.7' )->level );
	}

	public function test_bypass_list_is_trusted_and_manual_block_is_blocked_whatever_the_evidence() :void {
		TestDataFactory::insertBypass( '10.0.30.8' );
		$this->autoBlockWithEvidence( '10.0.30.9', 50.0 );
		TestDataFactory::insertManualBlock( '10.0.30.9' );

		$this->assertStanding( VisitorStanding::TRUSTED, [ VisitorStanding::REASON_BYPASS ], $this->resolve( '10.0.30.8' ) );
		$this->assertStanding( VisitorStanding::BLOCKED, [ VisitorStanding::REASON_MANUAL_BLOCK ], $this->resolve( '10.0.30.9' ) );
	}

	public function test_manual_block_comes_before_trust_shield_works_out_for_a_service() :void {
		$trustEveryone = static fn( array $services ) :array => \array_merge( $services, [
			'visitor' => 'Visitor',
			'unknown' => 'Unknown',
		] );
		add_filter( 'shield/trusted_services', $trustEveryone );
		try {
			TestDataFactory::insertManualBlock( '10.0.30.15' );

			$this->assertStanding( VisitorStanding::TRUSTED, [ VisitorStanding::REASON_TRUSTED_SERVICE ], $this->resolve( '10.0.30.16' ) );
			$this->assertStanding( VisitorStanding::BLOCKED, [ VisitorStanding::REASON_MANUAL_BLOCK ], $this->resolve( '10.0.30.15' ) );
		}
		finally {
			remove_filter( 'shield/trusted_services', $trustEveryone );
		}
	}

	public function test_records_from_before_visitor_standing_count_their_offenses_and_blocks() :void {
		TestDataFactory::insertAutoBlock( '10.0.30.17', [ 'offenses' => 4, 'blocked_at' => 0, 'last_access_at' => $this->now ] );
		TestDataFactory::insertAutoBlock( '10.0.30.18', [ 'offenses' => 10, 'blocked_at' => $this->now - 60, 'last_access_at' => $this->now - 30 ] );

		$this->assertStanding( VisitorStanding::DOUBTFUL, [ VisitorStanding::REASON_OFFENSES ], $this->resolve( '10.0.30.17' ) );
		$blocked = $this->resolve( '10.0.30.18' );
		$this->assertStanding( VisitorStanding::HOSTILE, [ VisitorStanding::REASON_HOSTILE_HOLD ], $blocked );
		$this->assertSame( $this->now - 30 + \HOUR_IN_SECONDS, $blocked->hostileUntil );
	}

	public function test_crowdsec_listing_is_hostile_unless_crowdsec_is_disabled() :void {
		TestDataFactory::insertCrowdsecBlock( '10.0.30.10' );

		$this->assertStanding( VisitorStanding::HOSTILE, [ VisitorStanding::REASON_CROWDSEC ], $this->resolve( '10.0.30.10' ) );

		$this->requireController()->opts->optSet( 'cs_block', 'disabled' );
		$this->assertSame( VisitorStanding::NORMAL, $this->resolve( '10.0.30.10' )->level );
	}

	public function test_unblocked_crowdsec_listing_is_doubtful_and_hostile_again_at_half_the_offense_limit() :void {
		TestDataFactory::insertCrowdsecBlock( '10.0.30.11', [ 'blocked_at' => $this->now - 60, 'unblocked_at' => $this->now - 30 ] );
		TestDataFactory::insertCrowdsecBlock( '10.0.30.12', [ 'blocked_at' => $this->now - 60, 'unblocked_at' => $this->now - 30 ] );
		$this->autoBlockWithEvidence( '10.0.30.12', 5.0 );

		$this->assertStanding( VisitorStanding::DOUBTFUL, [ VisitorStanding::REASON_CROWDSEC_UNBLOCKED ], $this->resolve( '10.0.30.11' ) );
		$this->assertSame( VisitorStanding::HOSTILE, $this->resolve( '10.0.30.12' )->level );
	}

	public function test_high_reputation_caps_offense_based_standing_but_not_a_crowdsec_listing() :void {
		foreach ( [ '10.0.30.13', '10.0.30.14' ] as $ip ) {
			TestDataFactory::insertBotSignal( $ip, [
				'auth_at'   => $this->now - 60,
				'altcha_at' => $this->now - 45,
				'notbot_at' => $this->now - 30,
			] );
		}
		$this->autoBlockWithEvidence( '10.0.30.13', 10.0 );
		TestDataFactory::insertCrowdsecBlock( '10.0.30.14' );

		$this->assertStanding(
			VisitorStanding::DOUBTFUL,
			[ VisitorStanding::REASON_OFFENSES, VisitorStanding::REASON_HIGH_REPUTATION ],
			$this->resolve( '10.0.30.13' )
		);
		$this->assertSame( VisitorStanding::HOSTILE, $this->resolve( '10.0.30.14' )->level );
	}

	private function autoBlockWithEvidence( string $ip, float $normalEvidence, array $columns = [] ) :void {
		$id = TestDataFactory::insertAutoBlock( $ip, [ 'blocked_at' => 0 ] );
		$this->requireController()->db_con->ip_rules->getQueryUpdater()->updateById( $id, \array_merge( [
			'normal_evidence' => $normalEvidence,
			'evidence_at'     => $this->now,
		], $columns ) );
	}

	private function resolve( string $ip ) :VisitorStanding {
		$this->resetIpCaches();
		return ( new ResolveStanding() )->setIP( $ip )->resolve( $this->now );
	}

	private function assertStanding( string $level, array $reasons, VisitorStanding $standing ) :void {
		$this->assertSame( $level, $standing->level );
		$this->assertSame( $reasons, $standing->reasons );
	}
}
