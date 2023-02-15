<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

class IsSecurityAdmin extends Base {

	public const SLUG = 'is_security_admin';

	protected function execConditionCheck() :bool {
		$con = $this->getCon();
		$secAdminCon = $con->getModule_SecAdmin()->getSecurityAdminController();
		if ( !isset( $con->this_req->is_security_admin ) ) {
			$con->this_req->is_security_admin = (
				!$secAdminCon->isEnabledSecAdmin()
				|| $secAdminCon->isCurrentUserRegisteredSecAdmin()
				|| $secAdminCon->getSecAdminTimeRemaining() > 0
			);
		}
		return $con->this_req->is_security_admin;
	}
}