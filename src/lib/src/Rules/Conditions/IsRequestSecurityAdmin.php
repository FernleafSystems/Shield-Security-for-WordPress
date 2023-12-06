<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Constants;

class IsRequestSecurityAdmin extends Base {

	public const SLUG = 'is_request_security_admin';

	public function getDescription() :string {
		return __( 'Is the request from a user authenticated as a Shield Security Admin.', 'wp-simple-firewall' );
	}

	protected function getPreviousResult() :?bool {
		return self::con()->this_req->is_security_admin;
	}

	protected function postExecConditionCheck( bool $result ) :void {
		self::con()->this_req->is_security_admin = $result;
	}

	protected function getSubConditions() :array {
		return [
			'logic'      => Constants::LOGIC_AND,
			'conditions' => [
				[
					'conditions' => IsUserAdminNormal::class,
				],
				[
					'logic'      => Constants::LOGIC_OR,
					'conditions' => [
						[
							'conditions' => RequestBypassesAllRestrictions::class,
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