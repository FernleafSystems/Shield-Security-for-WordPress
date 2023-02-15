<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Build\BuildRuleCoreShieldBase,
	Build\RuleTraits,
	Conditions,
	Responses
};

class RequestBypassesAllRestrictions extends BuildRuleCoreShieldBase {

	use RuleTraits\InstantExec;

	public const SLUG = 'shield/request_bypasses_all_restrictions';

	protected function getName() :string {
		return 'A Request That Bypasses Restrictions';
	}

	protected function getDescription() :string {
		return 'Does the request bypass all plugin restrictions.';
	}

	protected function getConditions() :array {
		return [
			'logic' => static::LOGIC_OR,
			'group' => [
				[
					'condition' => Conditions\IsForceOff::SLUG,
				],
				[
					'rule'         => IsPublicWebRequest::SLUG,
					'invert_match' => true,
				],
				[
					'rule' => IsTrustedBot::SLUG,
				],
				[
					'rule' => Shield\Modules\IPs\Rules\Build\IsPathWhitelisted::SLUG,
				],
				[
					'rule' => Shield\Modules\IPs\Rules\Build\IpWhitelisted::SLUG,
				],
			]
		];
	}

	protected function getResponses() :array {
		return [
			[
				'response' => Responses\SetRequestBypassesAllRestrictions::SLUG,
			],
		];
	}
}