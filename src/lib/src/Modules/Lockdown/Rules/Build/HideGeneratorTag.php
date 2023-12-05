<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Lockdown\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Conditions,
	Constants,
	Responses,
	WPHooksOrder
};

class HideGeneratorTag extends BuildRuleLockdownBase {

	public const SLUG = 'shield/hide_generator_tag';

	protected function getName() :string {
		return 'Hide Generator Tag';
	}

	protected function getDescription() :string {
		return 'Hide Generator Tag.';
	}

	protected function getWpHookLevel() :int {
		return WPHooksOrder::WP;
	}

	protected function getConditions() :array {
		return [
			'logic' => static::LOGIC_AND,
			'conditions' => [
				[
					'conditions' => Conditions\RequestBypassesAllRestrictions::class,
					'logic'      => Constants::LOGIC_INVERT
				],
			]
		];
	}

	protected function getResponses() :array {
		return [
			[
				'response' => Responses\HideGeneratorTag::class,
			],
		];
	}
}