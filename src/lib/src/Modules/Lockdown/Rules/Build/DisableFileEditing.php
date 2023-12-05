<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Lockdown\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Conditions,
	Constants,
	Responses
};

class DisableFileEditing extends BuildRuleLockdownBase {

	public const SLUG = 'shield/disable_file_editing';

	protected function getName() :string {
		return 'Disable File Editing';
	}

	protected function getDescription() :string {
		return 'Disable File editing from within the WP admin dashboard.';
	}

	protected function getConditions() :array {
		return [
			'conditions' => Conditions\RequestBypassesAllRestrictions::class,
			'logic'      => Constants::LOGIC_INVERT
		];
	}

	protected function getResponses() :array {
		return [
			[
				'response' => Responses\DisableFileEditing::class,
			],
		];
	}
}