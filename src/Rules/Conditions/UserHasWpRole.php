<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\{
	EnumLogic,
	EnumParameters
};
use FernleafSystems\Wordpress\Services\Services;

class UserHasWpRole extends Base {

	use Traits\TypeUser;

	public function getDescription() :string {
		return __( 'Does current user have the given WP role.', 'wp-simple-firewall' );
	}

	protected function execConditionCheck() :bool {
		$roles = Services::WpUsers()->getCurrentWpUser()->roles;
		return \in_array(
			\strtolower( $this->p->user_role ),
			\array_map( '\strtolower', \is_array( $roles ) ? $roles : (array)$roles )
		);
	}

	public function getParamsDef() :array {
		return [
			'user_role' => [
				'type'         => EnumParameters::TYPE_STRING,
				'label'        => __( 'User Role', 'wp-simple-firewall' ),
				'verify_regex' => '/^[a-zA-Z0-9_-]+$/'
			],
		];
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