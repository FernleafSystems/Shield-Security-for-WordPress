<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Build\BuildRuleCoreShieldBase,
	Conditions
};

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
			'logic' => static::LOGIC_AND,
			'group' => [
				[
					'condition'       => Conditions\WpIsWpcli::SLUG,
					'invert_match' => true,
				],
				[
					'condition' => Conditions\IsIpValidPublic::SLUG,
				],
				[
					'rule'         => IsServerLoopback::SLUG,
					'invert_match' => true,
				],
			]
		];
	}
}