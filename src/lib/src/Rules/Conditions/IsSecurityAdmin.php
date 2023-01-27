<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

class IsSecurityAdmin extends Base {

	public const SLUG = 'is_security_admin';

	protected function execConditionCheck() :bool {
		$secAdminCon = $this->getCon()
							->getModule_SecAdmin()
							->getSecurityAdminController();
		return ( new IsUserAdminNormal() )->setCon( $this->getCon() )->run() &&
			   (
				   !$secAdminCon->isEnabledSecAdmin()
				   || $secAdminCon->isCurrentUserRegisteredSecAdmin()
				   || $secAdminCon->getSecAdminTimeRemaining() > 0
			   );
	}

	public static function RequiredConditions() :array {
		return [
			IsUserAdminNormal::class
		];
	}
}