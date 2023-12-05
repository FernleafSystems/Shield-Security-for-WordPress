<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Constants;

class IsSecurityAdmin extends Base {

	public const SLUG = 'is_security_admin';

	protected function getPreviousResult() :?bool {
		return self::con()->this_req->is_security_admin;
	}

	protected function postExecConditionCheck( bool $result ) :void {
		self::con()->this_req->is_security_admin = $result;
	}

	protected function getSubConditions() :array {
		return [
			'logic' => Constants::LOGIC_OR,
			'conditions' => [
				[
					'conditions' => RequestBypassesAllRestrictions::class,
				],
				[
					'logic' => Constants::LOGIC_AND,
					'conditions' => [
						[
							'conditions' => IsUserAdminNormal::class,
						],
						[
							'conditions' => IsUserSecurityAdmin::class,
						],
					]
				]
			]
		];
	}
}