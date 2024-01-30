<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Build\BuildRuleCoreShieldBase,
	Build\RuleTraits,
};

/**
 * @deprecated 18.5.8
 */
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
		return [];
	}
}