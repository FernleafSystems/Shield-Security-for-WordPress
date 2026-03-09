<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\AddRule;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRulesCache;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\TracksOptionWrites;

class IpRulesCachePersistenceIntegrationTest extends ShieldIntegrationTestCase {

	use TracksOptionWrites;

	public function tear_down() {
		\delete_transient( $this->transientKeyForGroup( IpRulesCache::GROUP_NO_RULES ) );
		\delete_transient( $this->transientKeyForGroup( IpRulesCache::GROUP_COLLECTIONS ) );
		$this->stopTrackingOptionWrites();
		parent::tear_down();
	}

	public function test_plain_miss_does_not_write_legacy_ip_rules_option() :void {
		$this->requireDb( 'ip_rules' );
		$this->requireDb( 'ips' );

		$legacyOption = $this->requireController()->prefix( 'ip_rules_cache', '_' );
		\delete_option( $legacyOption );
		\delete_transient( $this->transientKeyForGroup( IpRulesCache::GROUP_NO_RULES ) );

		$this->startTrackingOptionWrites( [ $legacyOption ] );

		$status = new IpRuleStatus( '10.0.0.150' );
		$this->assertFalse( $status->hasRules() );
		$this->assertOptionWasNotWritten( $legacyOption );
		$this->assertTrue( IpRulesCache::Has( '10.0.0.150', IpRulesCache::GROUP_NO_RULES ) );
	}

	public function test_bypass_lookup_does_not_leak_between_ips_in_same_request() :void {
		$this->requireDb( 'ip_rules' );
		$this->requireDb( 'ips' );

		$bypassedIp = '10.0.0.151';
		TestDataFactory::insertBypass( $bypassedIp );
		\delete_transient( $this->transientKeyForGroup( IpRulesCache::GROUP_COLLECTIONS ) );
		$this->resetIpCaches();

		$this->assertFalse( ( new IpRuleStatus( '10.0.0.152' ) )->isBypass() );
		$this->assertTrue( ( new IpRuleStatus( $bypassedIp ) )->isBypass() );
	}

	public function test_range_miss_does_not_populate_no_rules_cache() :void {
		$this->requireDb( 'ip_rules' );
		$this->requireDb( 'ips' );

		$rangeLookup = '10.0.1.0/24';

		$status = new IpRuleStatus( $rangeLookup );
		$this->assertFalse( $status->hasRules() );
		$this->assertFalse( IpRulesCache::Has( $rangeLookup, IpRulesCache::GROUP_NO_RULES ) );
	}

	public function test_covering_range_add_clears_prior_single_ip_no_rules_miss() :void {
		$this->requireDb( 'ip_rules' );
		$this->requireDb( 'ips' );

		$ip = '10.0.2.44';

		$status = new IpRuleStatus( $ip );
		$this->assertFalse( $status->hasRules() );
		$this->assertTrue( IpRulesCache::Has( $ip, IpRulesCache::GROUP_NO_RULES ) );

		( new AddRule() )
			->setIP( '10.0.2.0/24' )
			->toManualBlacklist( 'range block' );

		$this->assertFalse( IpRulesCache::Has( $ip, IpRulesCache::GROUP_NO_RULES ) );

		$this->resetIpCaches();

		$status = new IpRuleStatus( $ip );
		$this->assertTrue( $status->hasManualBlock() );
		$this->assertTrue( $status->isBlocked() );
	}

	private function transientKeyForGroup( string $group ) :string {
		return $this->requireController()->prefix( 'ip_rules_cache_'.$group, '_' );
	}
}
