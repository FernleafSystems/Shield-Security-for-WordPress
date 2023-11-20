<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Lockdown\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Rules\Build\RequestBypassesAllRestrictions;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
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
			'group' => [
				[
					'rule'         => RequestBypassesAllRestrictions::SLUG,
					'invert_match' => true
				],
			]
		];
	}

	protected function getResponses() :array {
		return [
			[
				'response' => Responses\HideGeneratorTag::SLUG,
			],
		];
	}
}