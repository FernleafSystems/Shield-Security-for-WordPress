<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Build\Core;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Build\RuleTraits,
	Conditions,
	Responses
};

class IpWhitelisted extends BuildRuleIpsBase {

	use RuleTraits\InstantExec;

	public const SLUG = 'shield/is_ip_whitelisted';

	protected function getName() :string {
		return 'Is IP Whitelisted';
	}

	protected function getDescription() :string {
		return 'Test whether the current Request IP is whitelisted.';
	}

	protected function getConditions() :array {
		return [
			'conditions' => Conditions\IsIpWhitelisted::class
		];
	}

	protected function getResponses() :array {
		return [
			[
				'response' => Responses\UpdateIpRuleLastAccessAt::class,
			],
		];
	}
}