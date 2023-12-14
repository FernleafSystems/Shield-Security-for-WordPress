<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Build\RuleTraits,
	Conditions,
	Responses
};

class IpBlockedShield extends BuildRuleIpsBase {

	use RuleTraits\InstantExec;

	public const SLUG = 'shield/is_ip_blocked_shield';

	protected function getName() :string {
		return 'Is IP Shield Blocked';
	}

	protected function getDescription() :string {
		return 'Test whether the current Request IP is Blocked by Shield.';
	}

	protected function getConditions() :array {
		return [
			'conditions' => Conditions\IsIpBlockedByShield::class,
		];
	}

	protected function getResponses() :array {
		return [
			[
				'response' => Responses\UpdateIpRuleLastAccessAt::class,
			],
			[
				'response' => Responses\ProcessIpBlockedShield::class,
			],
		];
	}
}