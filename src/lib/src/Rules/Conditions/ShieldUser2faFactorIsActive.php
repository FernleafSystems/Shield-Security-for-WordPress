<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Conditions,
	Enum
};

class ShieldUser2faFactorIsActive extends ShieldUser2faBase {

	public function getName() :string {
		return __( 'Shield User 2FA Provider Activated', 'wp-simple-firewall' );
	}

	public function getDescription() :string {
		return __( 'Does user have a particular 2FA factor active on their profile.', 'wp-simple-firewall' );
	}

	protected function execConditionCheck() :bool {
		$matched = false;
		$user = $this->getUserFromSession();
		if ( !empty( $user ) ) {
			$provider = self::con()->comps->mfa->getProvidersForUser( $user )[ $this->p->provider ] ?? null;
			$matched = !empty( $provider ) && $provider->isProfileActive();
		}
		return $matched;
	}

	public function getParamsDef() :array {
		$providers = $this->get2faProviderForParamDef();
		return [
			'provider' => [
				'type'        => Enum\EnumParameters::TYPE_ENUM,
				'type_enum'   => \array_keys( $providers ),
				'enum_labels' => $providers,
				'label'       => __( '2FA Provider', 'wp-simple-firewall' ),
			],
		];
	}

	protected function getSubConditions() :array {
		return [
			'logic'      => Enum\EnumLogic::LOGIC_AND,
			'conditions' => [
				[
					'conditions' => Conditions\ShieldUser2faHasActive::class,
				],
				[
					'conditions' => $this->getDefaultConditionCheckCallable(),
				],
			]
		];
	}
}