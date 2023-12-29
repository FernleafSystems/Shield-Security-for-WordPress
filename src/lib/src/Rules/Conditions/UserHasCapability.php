<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\{
	EnumLogic,
	EnumParameters
};

/**
 * @property string $cap
 */
class UserHasCapability extends Base {

	use Traits\TypeUser;

	public const SLUG = 'user_has_capability';

	public function getDescription() :string {
		return __( "Is current user a logged-in WordPress administrator.", 'wp-simple-firewall' );
	}

	protected function execConditionCheck() :bool {
		return current_user_can( $this->cap );
	}

	public function getParamsDef() :array {
		return [
			'cap' => [
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