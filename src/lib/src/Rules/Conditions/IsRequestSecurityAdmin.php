<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumLogic;

class IsRequestSecurityAdmin extends Base {

	use Traits\TypeShield;

	public const SLUG = 'is_request_security_admin';

	public function getDescription() :string {
		return sprintf( __( 'Is the request from a user authenticated as a %s Admin.', 'wp-simple-firewall' ), self::con()->labels->Name );
	}

	protected function getPreviousResult() :?bool {
		return $this->req->is_security_admin;
	}

	protected function postExecConditionCheck( bool $result ) :void {
		$this->req->is_security_admin = $result;
	}

	protected function getSubConditions() :array {
		return [
			'logic'      => EnumLogic::LOGIC_AND,
			'conditions' => [
				[
					'conditions' => IsUserAdminNormal::class,
				],
				[
					'logic'      => EnumLogic::LOGIC_OR,
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