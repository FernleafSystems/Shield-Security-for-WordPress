<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Services\Services;

class IsUserAdminNormal extends Base {

	public const SLUG = 'is_user_admin_normal';

	protected function execConditionCheck() :bool {
		return ( new IsLoggedInNormal() )->setCon( $this->getCon() )->run()
			   && Services::WpUsers()->isUserAdmin();
	}

	public static function RequiredConditions() :array {
		return [
			IsLoggedInNormal::class,
		];
	}
}