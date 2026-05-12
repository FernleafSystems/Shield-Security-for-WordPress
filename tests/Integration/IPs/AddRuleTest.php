<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\LoadIpRules;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\Ops\Handler as IpRulesHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\AddRule;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Tests the AddRule business logic: rule creation, mutual exclusion
 * between bypass/block states, and validation.
 */
class AddRuleTest extends ShieldIntegrationTestCase {

	private function addRule() :AddRule {
		return new AddRule();
	}

	private function loadRulesForIpByType( string $ip, string $type ) :array {
		$loader = ( new LoadIpRules() )->setIP( $ip );
		$loader->wheres = [
			sprintf( "`ir`.`type`='%s'", $type )
		];
		return \array_values( $loader->select() );
	}

	// ── Rule creation persists ─────────────────────────────────────

	public function test_add_manual_block_persists() {
		$this->requireDb( 'ip_rules' );
		$this->requireDb( 'ips' );

		$rule = $this->addRule();
		$rule->setIP( '10.0.0.50' );
		$record = $rule->toManualBlacklist( 'test block' );

		$this->assertNotEmpty( $record );

		$this->resetIpCaches();
		$status = new IpRuleStatus( '10.0.0.50' );
		$this->assertTrue( $status->hasManualBlock() );
	}

	public function test_add_bypass_persists() {
		$this->requireDb( 'ip_rules' );
		$this->requireDb( 'ips' );

		$rule = $this->addRule();
		$rule->setIP( '10.0.0.51' );
		$record = $rule->toManualWhitelist( 'test bypass' );

		$this->assertNotEmpty( $record );

		$this->resetIpCaches();
		$status = new IpRuleStatus( '10.0.0.51' );
		$this->assertTrue( $status->isBypass() );
	}

	public function test_add_auto_block_persists() {
		$this->requireDb( 'ip_rules' );
		$this->requireDb( 'ips' );

		$rule = $this->addRule();
		$rule->setIP( '10.0.0.52' );
		$record = $rule->toAutoBlacklist();

		$this->assertNotEmpty( $record );

		$this->resetIpCaches();
		$status = new IpRuleStatus( '10.0.0.52' );
		$this->assertTrue( $status->isAutoBlacklisted() );
	}

	// ── Mutual exclusion ───────────────────────────────────────────

	public function test_adding_bypass_removes_existing_block() {
		$this->requireDb( 'ip_rules' );
		$this->requireDb( 'ips' );

		$ip = '10.0.0.53';

		// First block
		$rule = $this->addRule();
		$rule->setIP( $ip );
		$rule->toManualBlacklist( 'initial block' );

		$this->resetIpCaches();
		$this->assertTrue( ( new IpRuleStatus( $ip ) )->hasManualBlock() );

		// Then bypass - should remove the block
		$this->resetIpCaches();
		$rule2 = $this->addRule();
		$rule2->setIP( $ip );
		$rule2->toManualWhitelist( 'override bypass' );

		$this->resetIpCaches();
		$status = new IpRuleStatus( $ip );
		$this->assertTrue( $status->isBypass() );
		$this->assertFalse( $status->hasManualBlock(), 'Manual block should be removed when bypass is added' );
	}

	public function test_manual_block_replaces_auto_unblocked_crowdsec_rule() {
		$this->requireDb( 'ip_rules' );
		$this->requireDb( 'ips' );

		$ip = '10.0.0.57';
		$now = Services::Request()->ts();
		TestDataFactory::insertCrowdsecBlock( $ip, [
			'blocked_at'   => $now - HOUR_IN_SECONDS,
			'unblocked_at' => $now,
		] );

		$this->resetIpCaches();
		$this->addRule()
			 ->setIP( $ip )
			 ->toManualBlacklist( 'admin block' );

		$this->resetIpCaches();
		$manualRules = $this->loadRulesForIpByType( $ip, IpRulesHandler::T_MANUAL_BLOCK );
		$status = new IpRuleStatus( $ip );

		$this->assertCount( 1, $manualRules );
		$this->assertTrue( $status->hasManualBlock() );
		$this->assertTrue( $status->isBlockedByShield() );
		$this->assertCount( 0, $this->loadRulesForIpByType( $ip, IpRulesHandler::T_CROWDSEC ) );
	}

	public function test_manual_block_preserves_covering_crowdsec_range_rule() {
		$dbh = $this->requireDb( 'ip_rules' );
		$this->requireDb( 'ips' );

		$ip = '10.0.0.59';
		$now = Services::Request()->ts();
		$crowdsecRangeID = TestDataFactory::insertCrowdsecBlock( '10.0.0.0', [
			'cidr'         => 24,
			'is_range'     => true,
			'blocked_at'   => $now - HOUR_IN_SECONDS,
			'unblocked_at' => $now,
		] );

		$this->resetIpCaches();
		$this->addRule()
			 ->setIP( $ip )
			 ->toManualBlacklist( 'admin block' );

		$this->resetIpCaches();
		$this->assertNotEmpty( $dbh->getQuerySelector()->byId( $crowdsecRangeID ) );
		$this->assertCount( 1, $this->loadRulesForIpByType( $ip, IpRulesHandler::T_MANUAL_BLOCK ) );
	}

	public function test_manual_bypass_replaces_auto_unblocked_crowdsec_rule() {
		$this->requireDb( 'ip_rules' );
		$this->requireDb( 'ips' );

		$ip = '10.0.0.58';
		$now = Services::Request()->ts();
		TestDataFactory::insertCrowdsecBlock( $ip, [
			'blocked_at'   => $now - HOUR_IN_SECONDS,
			'unblocked_at' => $now,
		] );

		$this->resetIpCaches();
		$this->addRule()
			 ->setIP( $ip )
			 ->toManualWhitelist( 'admin bypass' );

		$this->resetIpCaches();
		$bypassRules = $this->loadRulesForIpByType( $ip, IpRulesHandler::T_MANUAL_BYPASS );
		$status = new IpRuleStatus( $ip );

		$this->assertCount( 1, $bypassRules );
		$this->assertTrue( $status->isBypass() );
		$this->assertFalse( $status->isBlocked() );
		$this->assertCount( 0, $this->loadRulesForIpByType( $ip, IpRulesHandler::T_CROWDSEC ) );
	}

	public function test_manual_bypass_replaces_exact_crowdsec_range_rule() {
		$dbh = $this->requireDb( 'ip_rules' );
		$this->requireDb( 'ips' );

		$range = '10.0.1.0/24';
		$now = Services::Request()->ts();
		$crowdsecRangeID = TestDataFactory::insertCrowdsecBlock( '10.0.1.0', [
			'cidr'         => 24,
			'is_range'     => true,
			'blocked_at'   => $now - HOUR_IN_SECONDS,
			'unblocked_at' => $now,
		] );

		$this->resetIpCaches();
		$this->addRule()
			 ->setIP( $range )
			 ->toManualWhitelist( 'admin bypass' );

		$this->resetIpCaches();
		$this->assertEmpty( $dbh->getQuerySelector()->byId( $crowdsecRangeID ) );
		$this->assertTrue( ( new IpRuleStatus( '10.0.1.55' ) )->isBypass() );
	}

	public function test_cannot_auto_block_bypassed_ip() {
		$this->requireDb( 'ip_rules' );
		$this->requireDb( 'ips' );

		$ip = '10.0.0.54';

		$rule = $this->addRule();
		$rule->setIP( $ip );
		$rule->toManualWhitelist( 'protected' );

		$this->resetIpCaches();

		$this->expectException( \Exception::class );
		$rule2 = $this->addRule();
		$rule2->setIP( $ip );
		$rule2->toAutoBlacklist();
	}

	public function test_cannot_manual_block_bypassed_ip() {
		$this->requireDb( 'ip_rules' );
		$this->requireDb( 'ips' );

		$ip = '10.0.0.55';

		$rule = $this->addRule();
		$rule->setIP( $ip );
		$rule->toManualWhitelist( 'protected' );

		$this->resetIpCaches();

		$this->expectException( \Exception::class );
		$rule2 = $this->addRule();
		$rule2->setIP( $ip );
		$rule2->toManualBlacklist( 'should fail' );
	}

	// ── Validation ─────────────────────────────────────────────────

	public function test_invalid_ip_throws() {
		$this->requireDb( 'ip_rules' );

		$this->expectException( \Exception::class );
		$rule = $this->addRule();
		$rule->setIP( 'not-an-ip' );
		$rule->toManualBlacklist();
	}

	public function test_adding_duplicate_bypass_throws() {
		$this->requireDb( 'ip_rules' );
		$this->requireDb( 'ips' );

		$ip = '10.0.0.56';

		$rule = $this->addRule();
		$rule->setIP( $ip );
		$rule->toManualWhitelist( 'first' );

		$this->resetIpCaches();

		$this->expectException( \Exception::class );
		$rule2 = $this->addRule();
		$rule2->setIP( $ip );
		$rule2->toManualWhitelist( 'duplicate' );
	}
}
