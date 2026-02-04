<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Enum
};

class ShieldUser2faHasActive extends ShieldUser2faBase {

	public function getName() :string {
		return sprintf( __( '%s User 2FA: Has Any Active', 'wp-simple-firewall' ), self::con()->labels->Name );
	}

	public function getDescription() :string {
		return __( 'User Has Any Active 2FA', 'wp-simple-firewall' );
	}

	protected function execConditionCheck() :bool {
		$user = $this->getUserFromSession();
		return !empty( $user ) && \count( self::con()->comps->mfa->getProvidersActiveForUser( $user ) ) > 0;
	}

	protected function getSubConditions() :array {
		return [
			'logic'      => Enum\EnumLogic::LOGIC_AND,
			'conditions' => [
				[
					'conditions' => $this->getDefaultConditionCheckCallable(),
				],
			]
		];
	}
}