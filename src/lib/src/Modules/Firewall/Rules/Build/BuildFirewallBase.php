<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Build\BuildRuleCoreShieldBase,
	Conditions,
	Constants,
	Responses
};

abstract class BuildFirewallBase extends BuildRuleCoreShieldBase {

	use ModConsumer;

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
		$excludedPaths = $this->getExcludedPaths();
		return [
			'logic'      => Constants::LOGIC_AND,
			'conditions' => \array_filter( [
				[
					'conditions' => Conditions\RequestBypassesAllRestrictions::class,
					'logic'      => Constants::LOGIC_INVERT
				],
				[
					'conditions' => Conditions\IsIpBlockedByShield::class,
					'logic'      => Constants::LOGIC_INVERT
				],
				[
					'conditions' => Conditions\RequestHasAnyParameters::class,
				],

				$this->opts()->isOpt( 'whitelist_admins', 'Y' ) ? [
					'conditions' => Conditions\IsUserAdminNormal::class,
					'logic'      => Constants::LOGIC_INVERT,
				] : null,

				!empty( $excludedPaths ) ? [
					'conditions' => Conditions\MatchRequestPaths::class,
					'logic'      => Constants::LOGIC_INVERT,
					'params'     => [
						'match_paths'    => $excludedPaths,
						'is_match_regex' => false,
					],
				] : null,

				[
					'logic'      => Constants::LOGIC_OR,
					'conditions' => \array_merge(
						$this->buildPatternMatchingSubConditions( 'simple' ),
						$this->buildPatternMatchingSubConditions( 'regex' )
					),
				]
			] )
		];
	}

	private function buildPatternMatchingSubConditions( string $type ) :array {
		return [
			[
				'conditions' => Conditions\MatchRequestParamQuery::class,
				'params'     => [
					'is_match_regex'  => $type === 'regex',
					'match_patterns'  => $this->getFirewallPatterns()[ $type ] ?? [],
					'match_category'  => static::SCAN_CATEGORY,
					'excluded_params' => $this->getExclusions(),
				],
			],
			[
				'conditions' => Conditions\MatchRequestParamPost::class,
				'params'     => [
					'is_match_regex'  => $type === 'regex',
					'match_patterns'  => $this->getFirewallPatterns()[ $type ] ?? [],
					'match_category'  => static::SCAN_CATEGORY,
					'excluded_params' => $this->getExclusions(),
				],
			],
		];
	}

	protected function getExcludedPaths() :array {
		return \array_keys( \array_filter( $this->getExclusions(), function ( $excl ) {
			return empty( $excl );
		} ) );
	}

	protected function getExclusions() :array {
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
				'response' => Responses\EventFire::class,
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
				'response' => Responses\FirewallBlock::class,
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