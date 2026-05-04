<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\FullPageDisplay\DisplayBlockPage;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Block\{
	BlockIpAddressCrowdsec,
	BlockIpAddressShield,
	BlockRecoveryRenderContracts,
	BlockTrafficRateLimitExceeded
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRulesCache;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\{
	RuntimeTestState,
	TestDataFactory
};

/**
 * @phpstan-type FixtureState array{
 *   options_snapshot:array<string,mixed>,
 *   ip_rule_ids:list<int>
 * }
 */
class PublicBlockRecoveryFixtureBuilder {

	private const OPTION_KEYS = [
		'antibot_high_reputation_minimum',
		'cs_block',
		'license_activated_at',
		'license_data',
		'license_deactivated_at',
		'user_auto_recover',
	];

	/**
	 * @return array{contract:array<string,mixed>,state:FixtureState}
	 */
	public function seed( string $scenario ) :array {
		RuntimeTestState::ensureDb( [ 'ips', 'ip_rules' ] );
		$con = RuntimeTestState::controller();
		$state = [
			'options_snapshot' => RuntimeTestState::snapshotOptions( self::OPTION_KEYS ),
			'ip_rule_ids'      => [],
		];

		try {
			$this->clearIpRuleLookupCaches();
			$ip = $con->this_req->ip ?: '203.0.113.66';
			switch ( $scenario ) {
				case 'shield-email':
					RuntimeTestState::applyPremiumCapabilities( [ 'user_auto_unblock' ] );
					$this->setOptions( [
						'antibot_high_reputation_minimum' => 999999,
						'user_auto_recover' => [ 'email' ],
					] );
					$state[ 'ip_rule_ids' ][] = $this->insertAutoBlockRange();
					break;

				case 'shield-auto':
					RuntimeTestState::applyPremiumCapabilities( [ 'user_auto_unblock' ] );
					$this->setOptions( [
						'antibot_high_reputation_minimum' => 999999,
						'user_auto_recover' => [ 'gasp' ],
					] );
					$state[ 'ip_rule_ids' ][] = $this->insertAutoBlockRange();
					break;

				case 'crowdsec-auto':
					$this->setOptions( [
						'cs_block' => 'block_with_unblock',
					] );
					$state[ 'ip_rule_ids' ][] = TestDataFactory::insertCrowdsecBlock( $ip );
					break;

				default:
					throw new \RuntimeException( 'Unknown public block recovery fixture scenario: '.$scenario );
			}

			return [
				'contract' => $this->contract( $ip ),
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
		$state = $this->normalizePersistedState( $state );
		foreach ( $state[ 'ip_rule_ids' ] as $id ) {
			$con->db_con->ip_rules->getQueryDeleter()->deleteById( $id );
		}
		$this->clearIpRuleLookupCaches();

		$this->restoreOptions( $state[ 'options_snapshot' ] );
	}

	/**
	 * @param array<string,mixed> $state
	 * @phpstan-return FixtureState
	 */
	private function normalizePersistedState( array $state ) :array {
		$ipRuleIds = \is_array( $state[ 'ip_rule_ids' ] ?? null ) ? $state[ 'ip_rule_ids' ] : [];
		return [
			'options_snapshot' => \is_array( $state[ 'options_snapshot' ] ?? null ) ? $state[ 'options_snapshot' ] : [],
			'ip_rule_ids'      => \array_values( \array_filter(
				\array_map( static fn( $id ) :int => (int)$id, $ipRuleIds ),
				static fn( int $id ) :bool => $id > 0
			) ),
		];
	}

	/**
	 * @param array<string,mixed> $updates
	 */
	private function setOptions( array $updates ) :void {
		$opts = RuntimeTestState::controller()->opts;
		foreach ( $updates as $key => $value ) {
			$opts->optSet( $key, $value );
		}
		$opts->store();
		RuntimeTestState::resetOptionsRuntimeCache();
	}

	/**
	 * @param array<string,mixed> $snapshot
	 */
	private function restoreOptions( array $snapshot ) :void {
		$con = RuntimeTestState::controller();
		foreach ( $snapshot as $key => $value ) {
			if ( $key === 'license_data' ) {
				$con->comps->license->updateLicenseData( \is_array( $value ) ? $value : [] );
				continue;
			}
			$con->opts->optSet( (string)$key, $value );
		}
		$con->opts->store();
		RuntimeTestState::resetOptionsRuntimeCache();
	}

	private function insertAutoBlockRange() :int {
		$id = TestDataFactory::insertAutoBlock( '0.0.0.0', [
			'cidr'     => 0,
			'is_range' => true,
		] );
		$this->clearIpRuleLookupCaches();
		return $id;
	}

	private function clearIpRuleLookupCaches() :void {
		IpRulesCache::Delete( IpRulesCache::COLLECTION_RANGES, IpRulesCache::GROUP_COLLECTIONS );
		IpRulesCache::ResetGroup( IpRulesCache::GROUP_NO_RULES );
	}

	/**
	 * @return array<string,mixed>
	 */
	private function contract( string $ip ) :array {
		$contracts = new PublicBlockRecoveryContractProbe();
		return [
			'ip'    => $ip,
			'urls'  => [
				'ip_shield'          => $this->blockPageUrl( BlockIpAddressShield::SLUG ),
				'ip_crowdsec'        => $this->blockPageUrl( BlockIpAddressCrowdsec::SLUG ),
				'traffic_rate_limit' => $this->blockPageUrl( BlockTrafficRateLimitExceeded::SLUG ),
			],
			'ids'   => [
				'ip_shield_email'       => $contracts->ids( 'ip-shield', 'email-unblock' ),
				'ip_shield_auto'        => $contracts->ids( 'ip-shield', 'auto-recover' ),
				'ip_crowdsec_auto'      => $contracts->ids( 'ip-crowdsec', 'auto-recover' ),
				'traffic_rate_limit_auto' => $contracts->ids( 'traffic-rate-limit', 'auto-recover' ),
			],
		];
	}

	private function blockPageUrl( string $renderSlug ) :string {
		return '/?'.\http_build_query( ActionData::Build( DisplayBlockPage::class, false, [
			'render_slug' => $renderSlug,
		] ) );
	}
}

class PublicBlockRecoveryContractProbe {

	use BlockRecoveryRenderContracts;

	/**
	 * @return array<string,string>
	 */
	public function ids( string $page, string $action ) :array {
		return $this->buildBlockRecoveryActionContract( $page, $action )[ 'ids' ];
	}
}
