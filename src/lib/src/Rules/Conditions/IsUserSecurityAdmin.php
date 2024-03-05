<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

class IsUserSecurityAdmin extends Base {

	use Traits\TypeShield;

	public const SLUG = 'is_user_security_admin';

	public function getDescription() :string {
		return __( "Is current user Security Admin.", 'wp-simple-firewall' );
	}

	protected function execConditionCheck() :bool {
		$secAdminCon = self::con()->comps->sec_admin;
		return (
			!$secAdminCon->isEnabledSecAdmin()
			|| $secAdminCon->isCurrentUserRegisteredSecAdmin()
			|| $secAdminCon->getSecAdminTimeRemaining() > 0
		);
	}
}