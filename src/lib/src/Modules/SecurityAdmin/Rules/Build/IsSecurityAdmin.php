<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Build\BuildRuleCoreShieldBase,
	Conditions,
	Responses
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Rules\Build\RequestBypassesAllRestrictions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\ModConsumer;

class IsSecurityAdmin extends BuildRuleCoreShieldBase {

	use ModConsumer;

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
					'rule' => RequestBypassesAllRestrictions::SLUG,
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