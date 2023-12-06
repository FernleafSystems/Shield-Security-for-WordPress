<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Build\BuildRuleCoreShieldBase,
	Build\RuleTraits,
	Conditions,
	Constants,
	Responses
};

class RequestIsSiteBlockdownBlocked extends BuildRuleCoreShieldBase {

	use RuleTraits\InstantExec;

	public const SLUG = 'shield/request_is_site_blockdown_blocked';

	protected function getName() :string {
		return 'The Request Is Blocked By Site Lockdown';
	}

	protected function getDescription() :string {
		return 'Does the request fail to meet any Site Lockdown exclusions and is to be blocked.';
	}

	protected function getConditions() :array {
		return [
			'conditions' => Conditions\RequestIsSiteBlockdownBlocked::class,
		];
	}

	protected function getResponses() :array {
		return [
			[
				'response' => Responses\ProcessRequestBlockedBySiteBlockdown::class,
			],
		];
	}
}