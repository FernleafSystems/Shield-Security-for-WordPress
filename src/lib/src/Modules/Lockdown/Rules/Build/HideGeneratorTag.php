<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Lockdown\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Build\BuildRuleCoreShieldBase,
	Responses
};

class HideGeneratorTag extends BuildRuleCoreShieldBase {

	const SLUG = 'shield/hide_generator_tag';

	protected function getName() :string {
		return 'Hide Generator Tag';
	}

	protected function getDescription() :string {
		return 'Hide Generator Tag.';
	}

	protected function getWpHookLevel() :int {
		return Shield\Rules\WPHooksOrder::WP;
	}

	protected function getResponses() :array {
		return [
			[
				'response' => Responses\HideGeneratorTag::SLUG,
			],
		];
	}
}