<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Build\BuildRuleCoreShieldBase,
	Conditions,
	Responses
};

abstract class BuildFirewallBase extends BuildRuleCoreShieldBase {

	const SCAN_CATEGORY = '';

	protected function getName() :string {
		return $this->getMod()
					->getStrings()
					->getOptionStrings( 'block_'.static::SCAN_CATEGORY )[ 'name' ];
	}

	protected function getDescription() :string {
		return sprintf( __( 'Check request parameters that trigger "%s" patterns.', 'wp-simple-firewall' ),
			$this->getName() );
	}

	protected function getPriority() :int {
		return 50;
	}

	protected function getConditions() :array {
		$conditions = [
			'logic' => static::LOGIC_AND,
			'group' => [
				[
					'rule'         => Plugin\Rules\Build\RequestBypassesAllRestrictions::SLUG,
					'invert_match' => true
				],
			]
		];

		$matchGroup = [
			'logic' => static::LOGIC_OR,
			'group' => [],
		];

		$simple = $this->getFirewallPatterns_Simple();
		if ( !empty( $simple ) ) {
			$matchGroup[ 'group' ][] = [
				'action' => Conditions\MatchRequestParamQuery::SLUG,
				'params' => [
					'is_match_regex' => false,
					'match_patterns' => $simple,
					'match_category' => static::SCAN_CATEGORY,
				],
			];
			$matchGroup[ 'group' ][] = [
				'action' => Conditions\MatchRequestParamPost::SLUG,
				'params' => [
					'is_match_regex' => false,
					'match_patterns' => $simple,
					'match_category' => static::SCAN_CATEGORY,
				],
			];
		}

		$regex = $this->getFirewallPatterns_Regex();
		if ( !empty( $regex ) ) {
			$matchGroup[ 'group' ][] = [
				'action' => Conditions\MatchRequestParamQuery::SLUG,
				'params' => [
					'is_match_regex' => true,
					'match_patterns' => $regex,
					'match_category' => static::SCAN_CATEGORY,
				],
			];
			$matchGroup[ 'group' ][] = [
				'action' => Conditions\MatchRequestParamPost::SLUG,
				'params' => [
					'is_match_regex' => true,
					'match_patterns' => $regex,
					'match_category' => static::SCAN_CATEGORY,
				],
			];
		}

		$conditions[ 'group' ][] = $matchGroup;

		return $conditions;
	}

	protected function getResponses() :array {
		return [
			[
				'action' => Responses\FirewallBlock::SLUG,
				'params' => [],
			],
		];
	}

	protected function getFirewallPatterns() :array {
		return $this->getOptions()->getDef( 'firewall_patterns' )[ static::SCAN_CATEGORY ] ?? [];
	}

	protected function getFirewallPatterns_Regex() :array {
		return $this->getFirewallPatterns()[ 'regex' ] ?? [];
	}

	protected function getFirewallPatterns_Simple() :array {
		return $this->getFirewallPatterns()[ 'simple' ] ?? [];
	}
}