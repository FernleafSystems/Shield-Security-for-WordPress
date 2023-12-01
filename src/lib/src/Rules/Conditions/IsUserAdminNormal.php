<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Constants;
use FernleafSystems\Wordpress\Services\Services;

class IsUserAdminNormal extends Base {

	public const SLUG = 'is_user_admin_normal';

	protected function execConditionCheck() :bool {
		return Services::WpUsers()->isUserAdmin();
	}

	protected function getSubConditions() :array {
		return [
			'logic'      => Constants::LOGIC_AND,
			'conditions' => [
				[
					'conditions' => IsLoggedInNormal::class,
				],
				[
					'conditions' => $this->getDefaultConditionCheckCallable(),
				],
			]
		];
	}
}