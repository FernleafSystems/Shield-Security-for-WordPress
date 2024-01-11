<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Build\BuildRuleCoreShieldBase,
	Build\RuleTraits,
	Conditions,
	Enum\EnumLogic,
};

/**
 * @deprecated 18.5.8
 */
class IsTrustedBot extends BuildRuleCoreShieldBase {

	use RuleTraits\InstantExec;

	public const SLUG = 'shield/is_trusted_bot';

	protected function getName() :string {
		return 'Is Trusted Bot';
	}

	protected function getDescription() :string {
		return 'Test whether the visitor is a trusted bot.';
	}

	protected function getConditions() :array {
		return [
			'logic' => EnumLogic::LOGIC_AND,
			'conditions' => [
				[
					'conditions' => Conditions\RequestIsServerLoopback::class,
					'logic'      => EnumLogic::LOGIC_INVERT,
				],
			]
		];
	}
}