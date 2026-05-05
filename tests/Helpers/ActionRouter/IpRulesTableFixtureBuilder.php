<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRulesCache;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\{
	RuntimeTestState,
	TestDataFactory
};

/**
 * @phpstan-type FixtureState array{ip_rule_ids:list<int>}
 */
class IpRulesTableFixtureBuilder {

	/**
	 * @return array{contract:array<string,mixed>,state:FixtureState}
	 */
	public function seed() :array {
		RuntimeTestState::ensureDb( [ 'ips', 'ip_rules' ] );

		$state = [
			'ip_rule_ids' => [],
		];

		try {
			$this->clearIpRuleLookupCaches();
			$ip = '198.51.100.221';
			$ruleID = TestDataFactory::insertManualBlock( $ip, [
				'label' => 'browser-ip-rule-delete',
			] );
			$state[ 'ip_rule_ids' ][] = $ruleID;
			$this->clearIpRuleLookupCaches();

			return [
				'contract' => [
					'ip'      => $ip,
					'rule_id' => $ruleID,
				],
				'state'    => $state,
			];
		}
		catch ( \Throwable $throwable ) {
			$this->cleanup( $state );
			throw $throwable;
		}
	}

	/**
	 * @param array<string,mixed> $state
	 */
	public function cleanup( array $state ) :void {
		RuntimeTestState::ensureDb( [ 'ips', 'ip_rules' ] );
		$con = RuntimeTestState::controller();

		foreach ( $this->normalizePersistedState( $state )[ 'ip_rule_ids' ] as $id ) {
			$con->db_con->ip_rules->getQueryDeleter()->deleteById( $id );
		}
		$this->clearIpRuleLookupCaches();
	}

	/**
	 * @param array<string,mixed> $state
	 * @phpstan-return FixtureState
	 */
	private function normalizePersistedState( array $state ) :array {
		$ipRuleIds = \is_array( $state[ 'ip_rule_ids' ] ?? null ) ? $state[ 'ip_rule_ids' ] : [];
		return [
			'ip_rule_ids' => \array_values( \array_filter(
				\array_map( static fn( $id ) :int => (int)$id, $ipRuleIds ),
				static fn( int $id ) :bool => $id > 0
			) ),
		];
	}

	private function clearIpRuleLookupCaches() :void {
		IpRulesCache::Delete( IpRulesCache::COLLECTION_RANGES, IpRulesCache::GROUP_COLLECTIONS );
		IpRulesCache::ResetGroup( IpRulesCache::GROUP_NO_RULES );
	}
}
