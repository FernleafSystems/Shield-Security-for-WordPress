<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall\Options;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Build\BuildRuleCoreShieldBase,
	Conditions,
	Responses
};

abstract class BuildFirewallBase extends BuildRuleCoreShieldBase {

	public const SCAN_CATEGORY = '';

	protected function getName() :string {
		return sprintf( '%s: %s', __( 'Firewall', 'wp-simple-firewall' ),
			$this->mod()
				 ->getStrings()
				 ->getOptionStrings( 'block_'.static::SCAN_CATEGORY )[ 'name' ] );
	}

	protected function getCommonAuditParamsMapping() :array {
		return \array_merge( parent::getCommonAuditParamsMapping(), [
			'term'  => 'match_pattern',
			'param' => 'match_request_param',
			'value' => 'match_request_value',
			'scan'  => 'match_category',
			'type'  => 'match_type',
		] );
	}

	protected function getDescription() :string {
		return sprintf( __( 'Check request parameters that trigger "%s" patterns.', 'wp-simple-firewall' ), $this->getName() );
	}

	protected function getConditions() :array {
		$conditions = [
			'logic' => static::LOGIC_AND,
			'group' => [
				[
					'rule'         => Plugin\Rules\Build\RequestBypassesAllRestrictions::SLUG,
					'invert_match' => true
				],
				[
					'rule'         => Shield\Modules\IPs\Rules\Build\IpBlockedShield::SLUG,
					'invert_match' => true
				],
				[
					'condition' => Conditions\RequestHasParameters::SLUG,
				],
			]
		];

		$excludedPaths = $this->getExcludedPaths();
		if ( !empty( $excludedPaths ) ) {
			$conditions[ 'group' ][] = [
				'condition' => Conditions\NotMatchRequestPath::SLUG,
				'params'    => [
					'is_match_regex' => false,
					'match_paths'    => $excludedPaths,
				],
			];
		}

		if ( $this->opts()->isOpt( 'whitelist_admins', 'Y' ) ) {
			$conditions[ 'group' ][] = [
				'condition'    => Conditions\IsUserAdminNormal::SLUG,
				'invert_match' => true,
			];
		}

		$firewallRulesGroup = [
			'logic' => static::LOGIC_OR,
			'group' => [],
		];

		$simple = $this->getFirewallPatterns_Simple();
		if ( !empty( $simple ) ) {
			$firewallRulesGroup[ 'group' ][] = [
				'condition' => Conditions\MatchRequestParamQuery::SLUG,
				'params'    => [
					'is_match_regex'  => false,
					'match_patterns'  => $simple,
					'match_category'  => static::SCAN_CATEGORY,
					'excluded_params' => $this->getExclusions(),
				],
			];
			$firewallRulesGroup[ 'group' ][] = [
				'condition' => Conditions\MatchRequestParamPost::SLUG,
				'params'    => [
					'is_match_regex'  => false,
					'match_patterns'  => $simple,
					'match_category'  => static::SCAN_CATEGORY,
					'excluded_params' => $this->getExclusions(),
				],
			];
		}

		$regex = $this->getFirewallPatterns_Regex();
		if ( !empty( $regex ) ) {
			$firewallRulesGroup[ 'group' ][] = [
				'condition' => Conditions\MatchRequestParamQuery::SLUG,
				'params'    => [
					'is_match_regex'  => true,
					'match_patterns'  => $regex,
					'match_category'  => static::SCAN_CATEGORY,
					'excluded_params' => $this->getExclusions(),
				],
			];
			$firewallRulesGroup[ 'group' ][] = [
				'condition' => Conditions\MatchRequestParamPost::SLUG,
				'params'    => [
					'is_match_regex'  => true,
					'match_patterns'  => $regex,
					'match_category'  => static::SCAN_CATEGORY,
					'excluded_params' => $this->getExclusions(),
				],
			];
		}

		$conditions[ 'group' ][] = $firewallRulesGroup;

		return $conditions;
	}

	protected function getExcludedPaths() :array {
		return \array_keys( \array_filter( $this->getExclusions(), function ( $excl ) {
			return empty( $excl );
		} ) );
	}

	protected function getExclusions() :array {
		/** @var Options $opts */
		$opts = $this->opts();
		$exclusions = $opts->getDef( 'default_whitelist' );
		foreach ( $opts->getCustomWhitelist() as $page => $params ) {
			if ( empty( $params ) || !\is_array( $params ) ) {
				continue;
			}
			if ( !isset( $exclusions[ $page ] ) ) {
				$exclusions[ $page ] = [
					'simple' => [],
				];
			}
			$exclusions[ $page ][ 'simple' ] = \array_merge( $exclusions[ $page ][ 'simple' ], $params );
		}
		return $exclusions;
	}

	protected function getResponses() :array {
		return [
			[
				'response' => Responses\EventFire::SLUG,
				'params'   => [
					'event'            => 'firewall_block',
					'offense_count'    => 1,
					'block'            => false,
					'audit_params'     => [
						'name' => $this->getName()
					],
					'audit_params_map' => $this->getCommonAuditParamsMapping(),
				],
			],
			[
				'response' => Responses\FirewallBlock::SLUG,
				'params'   => [],
			],
		];
	}

	protected function getFirewallPatterns() :array {
		return $this->opts()->getDef( 'firewall_patterns' )[ static::SCAN_CATEGORY ] ?? [];
	}

	protected function getFirewallPatterns_Regex() :array {
		return $this->getFirewallPatterns()[ 'regex' ] ?? [];
	}

	protected function getFirewallPatterns_Simple() :array {
		return $this->getFirewallPatterns()[ 'simple' ] ?? [];
	}
}