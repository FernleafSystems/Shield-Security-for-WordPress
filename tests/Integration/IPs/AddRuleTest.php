<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\AddRule;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

/**
 * Tests the AddRule business logic: rule creation, mutual exclusion
 * between bypass/block states, and validation.
 */
class AddRuleTest extends ShieldIntegrationTestCase {

	private function addRule() :AddRule {
		return new AddRule();
	}

	// ── Rule creation persists ─────────────────────────────────────

	public function test_add_manual_block_persists() {
		$this->requireDb( 'ip_rules' );
		$this->requireDb( 'ips' );

		$rule = $this->addRule();
		$rule->setIP( '10.0.0.50' );
		$record = $rule->toManualBlacklist( 'test block' );

		$this->assertNotEmpty( $record );
		$this->assertSame( 'test block', $record->label );

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
