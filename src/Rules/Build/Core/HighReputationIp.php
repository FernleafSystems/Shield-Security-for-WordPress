<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Build\Core;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Build\RuleTraits,
	Conditions,
	Responses,
};

class HighReputationIp extends BuildRuleIpsBase {

	use RuleTraits\InstantExec;

	public const SLUG = 'shield/is_high_reputation_ip';

	protected function getName() :string {
		return 'Is High Reputation IP';
	}

	protected function getDescription() :string {
		return 'Apply any overrides if the IP address has a high reputation score.';
	}

	protected function getConditions() :array {
		return [
			'conditions' => Conditions\IsIpHighReputation::class,
		];
	}

	protected function getResponses() :array {
		return [
			[
				'response' => Responses\PreventShieldIpAutoBlock::class,
			],
		];
	}
}