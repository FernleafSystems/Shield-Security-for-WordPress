<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Build\BuildRuleCoreShieldBase,
	Build\RuleTraits,
	Conditions,
	Responses
};

class IpBlockedCrowdsec extends BuildRuleCoreShieldBase {

	use RuleTraits\InstantExec;

	public const SLUG = 'shield/is_ip_blocked_crowdsec';

	protected function getName() :string {
		return 'Is IP CrowdSec Blocked';
	}

	protected function getDescription() :string {
		return 'Test whether the current Request IP is on CrowdSec and should be blocked.';
	}

	protected function getConditions() :array {
		return [
			'logic' => static::LOGIC_AND,
			'group' => [
				[
					'rule'         => Plugin\Rules\Build\RequestBypassesAllRestrictions::SLUG,
					'invert_match' => true
				],
				[
					'rule'         => IpBlockedShield::SLUG,
					'invert_match' => true
				],
				[
					'condition'    => Conditions\IsIpHighReputation::SLUG,
					'invert_match' => true
				],
				[
					'condition' => Conditions\IsIpBlockedCrowdsec::SLUG,
				],
			]
		];
	}

	protected function getResponses() :array {
		return [
			[
				'response' => Responses\SetIpBlockedCrowdsec::SLUG,
			],
		];
	}
}