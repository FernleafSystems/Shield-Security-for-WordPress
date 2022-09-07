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

class IpBlockedShield extends BuildRuleCoreShieldBase {

	use RuleTraits\InstantExec;

	const SLUG = 'shield/is_ip_blocked_shield';

	protected function getName() :string {
		return 'Is IP Shield Blocked';
	}

	protected function getDescription() :string {
		return 'Test whether the current Request IP is Blocked by Shield.';
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
					'logic' => static::LOGIC_OR,
					'group' => [
						[
							'condition' => Conditions\IsIpBlockedManual::SLUG,
						],
						[
							'logic' => static::LOGIC_AND,
							'group' => [
								[
									'condition' => Conditions\IsIpBlockedAuto::SLUG,
								],
								[
									'condition'    => Conditions\IsIpHighReputation::SLUG,
									'invert_match' => true
								],
							]
						]
					]
				]
			]
		];
	}

	protected function getResponses() :array {
		return [
			[
				'response' => Responses\SetIpBlockedShield::SLUG,
			],
		];
	}
}