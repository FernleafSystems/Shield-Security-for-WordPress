<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRulesCache;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\{
	RuntimeTestState,
	TestDataFactory
};

/**
 * @phpstan-type FixtureState array{ip:string,ip_rule_ids:list<int>}
 * @phpstan-type RuleEvidence array{
 *   exists:bool,
 *   id:int,
 *   ip:string,
 *   type:string,
 *   cidr:int,
 *   is_range:bool,
 *   offenses:int,
 *   is_blocked:bool,
 *   label:string,
 *   blocked_at:int,
 *   unblocked_at:int
 * }
 * @phpstan-type FixtureInspection array{
 *   ip:string,
 *   rules:list<RuleEvidence>
 * }
 */
class IpRulesTableFixtureBuilder {

	/**
	 * @return array{contract:array<string,mixed>,state:FixtureState}
	 */
	public function seed() :array {
		RuntimeTestState::ensureDb( [ 'ips', 'ip_rules' ] );

		$state = [
			'ip'          => '198.51.100.221',
			'ip_rule_ids' => [],
		];

		try {
			$this->clearIpRuleLookupCaches();
			$ruleID = TestDataFactory::insertManualBlock( $state[ 'ip' ], [
				'label' => 'browser-ip-rule-delete',
			] );
			$state[ 'ip_rule_ids' ][] = $ruleID;
			$this->clearIpRuleLookupCaches();

			return [
				'contract' => [
					'ip'      => $state[ 'ip' ],
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
	 * @return FixtureInspection
	 */
	public function inspect( array $state ) :array {
		RuntimeTestState::ensureDb( [ 'ips', 'ip_rules' ] );
		$state = $this->normalizePersistedState( $state );
		$con = RuntimeTestState::controller();
		$rules = [];

		foreach ( $state[ 'ip_rule_ids' ] as $id ) {
			$record = $con->db_con->ip_rules->getQuerySelector()->byId( $id );
			$rules[] = $this->ruleEvidence( $record );
		}

		return [
			'ip'    => $state[ 'ip' ],
			'rules' => $rules,
		];
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
			'ip'          => \trim( (string)( $state[ 'ip' ] ?? '' ) ),
			'ip_rule_ids' => \array_values( \array_filter(
				\array_map( static fn( $id ) :int => (int)$id, $ipRuleIds ),
				static fn( int $id ) :bool => $id > 0
			) ),
		];
	}

	/**
	 * @param object|null $record
	 * @return RuleEvidence
	 */
	private function ruleEvidence( ?object $record ) :array {
		$ip = '';
		if ( $record !== null ) {
			$ipRecord = RuntimeTestState::controller()->db_con->ips
				->getQuerySelector()
				->byId( (int)( $record->ip_ref ?? 0 ) );
			$ip = (string)( $ipRecord->ip ?? '' );
		}

		$blockedAt = (int)( $record->blocked_at ?? 0 );
		$unblockedAt = (int)( $record->unblocked_at ?? 0 );
		return [
			'exists'       => $record !== null,
			'id'           => (int)( $record->id ?? 0 ),
			'ip'           => $ip,
			'type'         => (string)( $record->type ?? '' ),
			'cidr'         => (int)( $record->cidr ?? 0 ),
			'is_range'     => (bool)( $record->is_range ?? false ),
			'offenses'     => (int)( $record->offenses ?? 0 ),
			'is_blocked'   => $blockedAt > 0 && $unblockedAt === 0,
			'label'        => (string)( $record->label ?? '' ),
			'blocked_at'   => $blockedAt,
			'unblocked_at' => $unblockedAt,
		];
	}

	private function clearIpRuleLookupCaches() :void {
		IpRulesCache::Delete( IpRulesCache::COLLECTION_RANGES, IpRulesCache::GROUP_COLLECTIONS );
		IpRulesCache::ResetGroup( IpRulesCache::GROUP_NO_RULES );
	}
}
