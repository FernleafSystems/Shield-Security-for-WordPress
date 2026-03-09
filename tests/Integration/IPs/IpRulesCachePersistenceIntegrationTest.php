<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\IPs;

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
		$this->assertIsArray( \get_transient( $this->transientKeyForGroup( IpRulesCache::GROUP_NO_RULES ) ) );
	}

	public function test_bypass_collection_is_cached_under_bypass_key() :void {
		$this->requireDb( 'ip_rules' );
		$this->requireDb( 'ips' );

		$ip = '10.0.0.151';
		TestDataFactory::insertBypass( $ip );
		\delete_transient( $this->transientKeyForGroup( IpRulesCache::GROUP_COLLECTIONS ) );
		$this->resetIpCaches();

		$status = new IpRuleStatus( $ip );
		$this->assertTrue( $status->isBypass() );

		$cache = \get_transient( $this->transientKeyForGroup( IpRulesCache::GROUP_COLLECTIONS ) );
		$this->assertIsArray( $cache );
		$this->assertArrayHasKey( IpRulesCache::COLLECTION_BYPASS, $cache );
		$this->assertArrayHasKey( 'data', $cache[ IpRulesCache::COLLECTION_BYPASS ] );
		$this->assertNotEmpty( $cache[ IpRulesCache::COLLECTION_BYPASS ][ 'data' ] );
	}

	private function transientKeyForGroup( string $group ) :string {
		return $this->requireController()->prefix( 'ip_rules_cache_'.$group, '_' );
	}
}
