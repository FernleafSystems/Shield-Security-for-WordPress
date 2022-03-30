<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Build\BuildRuleCoreShieldBase,
	Conditions
};

class RequestBypassesAllRestrictions extends BuildRuleCoreShieldBase {

	const SLUG = 'shield/request_bypasses_all_restrictions';

	protected function getName() :string {
		return 'A Request That Bypasses Restrictions';
	}

	protected function getDescription() :string {
		return 'Does the request bypass all plugin restrictions.';
	}

	protected function getPriority() :int {
		return 15;
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
					'condition' => Conditions\IsIpWhitelisted::SLUG,
				],
				[
					'rule' => IsTrustedBot::SLUG,
				],
			]
		];
	}
}