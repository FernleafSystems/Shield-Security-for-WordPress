<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumLogic;

class IsUserAdminNormal extends Base {

	use Traits\TypeUser;

	public const SLUG = 'is_user_admin_normal';

	public function getDescription() :string {
		return __( "Is current logged-in user a WordPress administrator.", 'wp-simple-firewall' );
	}

	protected function getSubConditions() :array {
		return [
			'logic'      => EnumLogic::LOGIC_AND,
			'conditions' => [
				[
					'conditions' => IsLoggedInNormal::class,
				],
				[
					'conditions' => UserHasWpCapability::class,
					'params'     => [
						'user_cap' => 'manage_options',
					],
				],
			]
		];
	}
}