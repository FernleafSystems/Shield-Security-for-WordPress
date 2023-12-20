<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumLogic;
use FernleafSystems\Wordpress\Services\Services;

class IsUserAdminNormal extends Base {

	use Traits\TypeUser;

	public const SLUG = 'is_user_admin_normal';

	public function getDescription() :string {
		return __( "Is current user a logged-in WordPress administrator.", 'wp-simple-firewall' );
	}

	protected function execConditionCheck() :bool {
		return Services::WpUsers()->isUserAdmin();
	}

	protected function getSubConditions() :array {
		return [
			'logic'      => EnumLogic::LOGIC_AND,
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