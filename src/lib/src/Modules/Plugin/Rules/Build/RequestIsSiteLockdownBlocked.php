<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Build\BuildRuleCoreShieldBase,
	Build\RuleTraits,
	Conditions,
	Responses
};

class RequestIsSiteLockdownBlocked extends BuildRuleCoreShieldBase {

	use RuleTraits\InstantExec;

	public const SLUG = 'shield/request_is_site_lockdown_blocked';

	protected function getName() :string {
		return 'The Request Is Blocked By Site Lockdown';
	}

	protected function getDescription() :string {
		return 'Does the request fail to mean any Site Lockdown exclusions and is to be blocked.';
	}

	protected function getConditions() :array {
		return [
			'logic' => static::LOGIC_AND,
			'group' => [
				[
					'condition'    => Conditions\IsSiteLockdownActive::SLUG,
				],
				[
					'condition'    => Conditions\IsForceOff::SLUG,
					'invert_match' => true,
				],
				[
					'rule' => IsPublicWebRequest::SLUG,
				],
				[
					'rule'         => Shield\Modules\IPs\Rules\Build\IpWhitelisted::SLUG,
					'invert_match' => true,
				],
			]
		];
	}

	protected function getResponses() :array {
		return [
			[
				'response' => Responses\SetRequestIsSiteLockdownBlocked::SLUG,
			],
		];
	}
}