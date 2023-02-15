<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Build\BuildRuleCoreShieldBase,
	Conditions,
	Responses
};

class IsSecurityAdmin extends BuildRuleCoreShieldBase {

	public const SLUG = 'shield/is_security_admin';

	protected function getName() :string {
		return 'Is Security Admin';
	}

	protected function getDescription() :string {
		return 'Is Security Admin.';
	}

	protected function getConditions() :array {
		return [
			'logic' => static::LOGIC_OR,
			'group' => [
				[
					'rule' => Plugin\Rules\Build\RequestBypassesAllRestrictions::SLUG,
				],
				[
					'logic' => static::LOGIC_AND,
					'group' => [
						[
							'condition' => Conditions\IsUserAdminNormal::SLUG,
						],
						[
							'condition' => Conditions\IsSecurityAdmin::SLUG,
						],
					]
				]
			]
		];
	}

	protected function getResponses() :array {
		return [
			[
				'response' => Responses\SetSecurityAdmin::SLUG,
			],
		];
	}
}