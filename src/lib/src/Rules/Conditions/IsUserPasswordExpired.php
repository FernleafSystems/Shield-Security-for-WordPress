<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Password\QueryUserPasswordExpired;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumLogic;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\WPHooksOrder;
use FernleafSystems\Wordpress\Services\Services;

class IsUserPasswordExpired extends Base {

	use Traits\TypeUser;

	public const SLUG = 'is_user_password_expired';

	public static function MinimumHook() :int {
		return WPHooksOrder::INIT;
	}

	public function getDescription() :string {
		return __( 'Is current user password expired.', 'wp-simple-firewall' );
	}

	protected function execConditionCheck() :bool {
		return ( new QueryUserPasswordExpired() )->check( Services::WpUsers()->getCurrentWpUser() );
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