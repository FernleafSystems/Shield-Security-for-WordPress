<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\Ops\Handler as IpRulesHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Services;

/**
 * IpRuleStatus is the single point where "is this IP blocked?" gets answered.
 * Every boolean security decision path needs coverage.
 */
class IpRuleStatusTest extends ShieldIntegrationTestCase {

	private function makeStatus( string $ip ) :IpRuleStatus {
		$this->resetIpCaches();
		return new IpRuleStatus( $ip );
	}

	// ── No rules ───────────────────────────────────────────────────

	public function test_fresh_ip_has_no_rules() {
		$this->requireDb( 'ip_rules' );
		$this->requireDb( 'ips' );

		$status = $this->makeStatus( '192.0.2.100' );
		$this->assertFalse( $status->hasRules() );
		$this->assertFalse( $status->isBlocked() );
		$this->assertSame( 0, $status->getOffenses() );
		$this->assertSame( '', $status->getBlockType() );
	}

	// ── Manual block ───────────────────────────────────────────────

	public function test_manual_block_flags() {
		$this->requireDb( 'ip_rules' );
		$this->requireDb( 'ips' );

		TestDataFactory::insertManualBlock( '192.0.2.101' );

		$status = $this->makeStatus( '192.0.2.101' );
		$this->assertTrue( $status->isBlocked() );
		$this->assertTrue( $status->hasManualBlock() );
		$this->assertTrue( $status->isBlockedByShield() );
		$this->assertSame( IpRulesHandler::T_MANUAL_BLOCK, $status->getBlockType() );
	}

	// ── Auto block (active) ───────────────────────────────────────

	public function test_auto_block_active() {
		$this->requireDb( 'ip_rules' );
		$this->requireDb( 'ips' );

		$now = Services::Request()->ts();
		TestDataFactory::insertAutoBlock( '192.0.2.102', [
			'blocked_at'     => $now,
			'unblocked_at'   => 0,
			'last_access_at' => $now,
		] );

		$status = $this->makeStatus( '192.0.2.102' );
		$this->assertTrue( $status->hasAutoBlock() );
		$this->assertTrue( $status->isBlockedByShield() );
		$this->assertTrue( $status->isBlocked() );
	}

	// ── Auto block (unblocked) ────────────────────────────────────

	public function test_auto_block_unblocked() {
		$this->requireDb( 'ip_rules' );
		$this->requireDb( 'ips' );

		$now = Services::Request()->ts();
		TestDataFactory::insertAutoBlock( '192.0.2.103', [
			'blocked_at'     => $now - 3600,
			'unblocked_at'   => $now,
			'last_access_at' => $now,
		] );

		$status = $this->makeStatus( '192.0.2.103' );
		$this->assertFalse( $status->hasAutoBlock(), 'Auto-block should not be active when unblocked_at > blocked_at' );
		$this->assertTrue( $status->isUnBlocked() );
		$this->assertFalse( $status->isBlocked() );
	}

	// ── Bypass overrides block ────────────────────────────────────

	public function test_bypass_overrides_manual_block() {
		$this->requireDb( 'ip_rules' );
		$this->requireDb( 'ips' );

		TestDataFactory::insertManualBlock( '192.0.2.104' );
		TestDataFactory::insertBypass( '192.0.2.104' );

		$status = $this->makeStatus( '192.0.2.104' );
		$this->assertTrue( $status->isBypass() );
		$this->assertFalse( $status->isBlocked(), 'Bypass should override block' );
		$this->assertFalse( $status->isBlockedByShield() );
	}

	// ── CrowdSec block ────────────────────────────────────────────

	public function test_crowdsec_block() {
		$this->requireDb( 'ip_rules' );
		$this->requireDb( 'ips' );

		$now = Services::Request()->ts();
		TestDataFactory::insertCrowdsecBlock( '192.0.2.105', [
			'blocked_at'   => $now,
			'unblocked_at' => 0,
		] );

		$status = $this->makeStatus( '192.0.2.105' );
		$this->assertTrue( $status->hasCrowdsecBlock() );
		$this->assertTrue( $status->isBlockedByCrowdsec() );
		$this->assertTrue( $status->isBlocked() );
	}

	// ── CrowdSec respects bypass ──────────────────────────────────

	public function test_crowdsec_respects_bypass() {
		$this->requireDb( 'ip_rules' );
		$this->requireDb( 'ips' );

		$now = Services::Request()->ts();
		TestDataFactory::insertCrowdsecBlock( '192.0.2.106', [
			'blocked_at'   => $now,
			'unblocked_at' => 0,
		] );
		TestDataFactory::insertBypass( '192.0.2.106' );

		$status = $this->makeStatus( '192.0.2.106' );
		$this->assertTrue( $status->isBypass() );
		$this->assertFalse( $status->isBlockedByCrowdsec() );
		$this->assertFalse( $status->isBlocked() );
	}

	// ── Offense count ─────────────────────────────────────────────

	public function test_offense_count_from_auto_block() {
		$this->requireDb( 'ip_rules' );
		$this->requireDb( 'ips' );

		TestDataFactory::insertAutoBlock( '192.0.2.107', [
			'offenses'       => 5,
			'last_access_at' => Services::Request()->ts(),
		] );

		$status = $this->makeStatus( '192.0.2.107' );
		$this->assertSame( 5, $status->getOffenses() );
	}

	public function test_offense_count_zero_without_auto_block() {
		$this->requireDb( 'ip_rules' );
		$this->requireDb( 'ips' );

		TestDataFactory::insertManualBlock( '192.0.2.108' );

		$status = $this->makeStatus( '192.0.2.108' );
		$this->assertSame( 0, $status->getOffenses() );
	}

	// ── Cache clearing ────────────────────────────────────────────

	public function test_clear_status_forces_fresh_lookup() {
		$this->requireDb( 'ip_rules' );
		$this->requireDb( 'ips' );

		$ip = '192.0.2.109';

		$status = $this->makeStatus( $ip );
		$this->assertFalse( $status->isBlocked() );

		TestDataFactory::insertManualBlock( $ip );

		// Without clearing, the cached result would still say "not blocked"
		IpRuleStatus::ClearStatusForIP( $ip );

		$status2 = new IpRuleStatus( $ip );
		$this->assertTrue( $status2->isBlocked(), 'After ClearStatusForIP, a fresh DB lookup should detect the new block.' );
	}

	// ── isAutoBlacklisted (on list but not fully blocked) ─────────

	public function test_auto_blacklisted_but_not_blocked() {
		$this->requireDb( 'ip_rules' );
		$this->requireDb( 'ips' );

		$now = Services::Request()->ts();
		TestDataFactory::insertAutoBlock( '192.0.2.110', [
			'blocked_at'     => 0,
			'unblocked_at'   => 0,
			'offenses'       => 2,
			'last_access_at' => $now,
		] );

		$status = $this->makeStatus( '192.0.2.110' );
		$this->assertTrue( $status->isAutoBlacklisted() );
		$this->assertFalse( $status->hasAutoBlock(), 'blocked_at=0 means not actively blocked' );
		$this->assertFalse( $status->isBlocked() );
	}
}
