<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\WPHooksOrder;
use FernleafSystems\Wordpress\Services\Services;

class IsNotLoggedInNormal extends Base {

	const SLUG = 'is_not_logged_in_normal';

	protected function execConditionCheck() :bool {
		return !Services::WpUsers()->isUserLoggedIn();
	}

	public static function MinimumHook() :int {
		return WPHooksOrder::INIT;
	}
}