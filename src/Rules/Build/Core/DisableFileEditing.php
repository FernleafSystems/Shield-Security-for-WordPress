<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Build\Core;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Conditions,
	Enum,
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
			'logic'      => Enum\EnumLogic::LOGIC_AND,
			'conditions' => [
				[
					'conditions' => Conditions\RequestBypassesAllRestrictions::class,
					'logic'      => Enum\EnumLogic::LOGIC_INVERT
				],
				[
					'conditions' => Conditions\ShieldConfigurationOption::class,
					'params'     => [
						'name'        => 'disable_file_editing',
						'match_type'  => Enum\EnumMatchTypes::MATCH_TYPE_EQUALS,
						'match_value' => 'Y',
					]
				],
			]
		];
	}

	protected function getResponses() :array {
		return [
			[
				'response' => Responses\DisableFileEditing::class,
			],
			[
				'response' => Responses\PhpSetDefine::class,
				'params'   => [
					'name'  => 'DISALLOW_FILE_EDIT',
					'value' => true,
				]
			],
		];
	}
}