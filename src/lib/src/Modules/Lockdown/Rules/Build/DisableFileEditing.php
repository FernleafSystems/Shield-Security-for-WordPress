<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Lockdown\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Build\BuildRuleCoreShieldBase,
	Responses
};

class DisableFileEditing extends BuildRuleCoreShieldBase {

	const SLUG = 'shield/disable_file_editing';

	protected function getName() :string {
		return 'Disable File Editing';
	}

	protected function getDescription() :string {
		return 'Disable File editing from within the WP admin dashboard.';
	}

	protected function getConditions() :array {
		return [
			'logic' => static::LOGIC_AND,
			'group' => [
				[
					'rule'         => Shield\Modules\Plugin\Rules\Build\RequestBypassesAllRestrictions::SLUG,
					'invert_match' => true
				],
			]
		];
	}

	protected function getResponses() :array {
		return [
			[
				'response' => Responses\DisableFileEditing::SLUG,
			],
		];
	}
}