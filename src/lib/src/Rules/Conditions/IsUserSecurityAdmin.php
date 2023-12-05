<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

class IsUserSecurityAdmin extends Base {

	public const SLUG = 'is_user_security_admin';

	public function getDescription() :string {
		return __( "Is current user Security Admin.", 'wp-simple-firewall' );
	}

	protected function execConditionCheck() :bool {
		$secAdminCon = self::con()->getModule_SecAdmin()->getSecurityAdminController();
		return (
			!$secAdminCon->isEnabledSecAdmin()
			|| $secAdminCon->isCurrentUserRegisteredSecAdmin()
			|| $secAdminCon->getSecAdminTimeRemaining() > 0
		);
	}
}