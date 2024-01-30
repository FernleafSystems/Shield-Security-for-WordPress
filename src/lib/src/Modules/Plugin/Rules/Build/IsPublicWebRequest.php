<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Build\BuildRuleCoreShieldBase,
	Conditions,
	Enum\EnumLogic
};

/**
 * @deprecated 18.5.8
 */
class IsPublicWebRequest extends BuildRuleCoreShieldBase {

	public const SLUG = 'shield/is_public_web_request';

	protected function getName() :string {
		return 'Is Public Web Request';
	}

	protected function getDescription() :string {
		return 'Is a public web request.';
	}

	protected function getConditions() :array {
		return [
			'logic' => EnumLogic::LOGIC_AND,
			'conditions' => [
				[
					'conditions' => Conditions\WpIsWpcli::class,
					'logic'      => EnumLogic::LOGIC_INVERT,
				],
				[
					'conditions' => Conditions\IsIpValidPublic::class,
				],
			]
		];
	}
}