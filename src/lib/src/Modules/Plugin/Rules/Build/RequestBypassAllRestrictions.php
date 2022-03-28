<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Build\BuildRuleCoreShieldBase,
	Conditions
};

class RequestBypassAllRestrictions extends BuildRuleCoreShieldBase {

	const SLUG = 'shield/request_bypass_all_restrictions';

	protected function getName() :string {
		return 'A Request That Bypasses Restrictions';
	}

	protected function getDescription() :string {
		return 'Does the request bypass all plugin restrictions.';
	}

	protected function getSlug() :string {
		return 'shield/is_public_web_request';
	}

	protected function getPriority() :int {
		return 10;
	}

	protected function getConditions() :array {
		return [
			'logic' => static::LOGIC_OR,
			'group' => [
				[
					'action' => Conditions\WpIsWpcli::SLUG,
				],
				[
					'action' => Conditions\IsIpWhitelisted::SLUG,
				],
				[
					'rule' => IsTrustedBot::SLUG,
				],
				[
					'rule'         => IsPublicWebRequest::SLUG,
					'invert_match' => true,
				],
			]
		];
	}
}