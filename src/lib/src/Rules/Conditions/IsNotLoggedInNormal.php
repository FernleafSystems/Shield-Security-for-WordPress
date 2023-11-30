<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

class IsNotLoggedInNormal extends IsLoggedInNormal {

	public const SLUG = 'is_not_logged_in_normal';

	protected function execConditionCheck() :bool {
		return !parent::execConditionCheck();
	}
}