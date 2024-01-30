<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\{
	EnumLogic,
	EnumParameters
};

class UserHasWpCapability extends Base {

	use Traits\TypeUser;

	public function getDescription() :string {
		return __( "Is current user a logged-in WordPress administrator.", 'wp-simple-firewall' );
	}

	protected function execConditionCheck() :bool {
		return \function_exists( '\current_user_can' ) && current_user_can( $this->p->user_cap );
	}

	public function getParamsDef() :array {
		return [
			'user_cap' => [
				'type'         => EnumParameters::TYPE_STRING,
				'label'        => __( 'Capability Key', 'wp-simple-firewall' ),
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