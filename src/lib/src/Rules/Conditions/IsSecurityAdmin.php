<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Services\Services;

class IsSecurityAdmin extends Base {

	const SLUG = 'is_security_admin';

	protected function execConditionCheck() :bool {
		$secAdminCon = $this->getCon()
							->getModule_SecAdmin()
							->getSecurityAdminController();
		return $secAdminCon->isRegisteredSecAdminUser( Services::WpUsers()->getCurrentWpUser() )
			   || $secAdminCon->getSecAdminTimeRemaining() > 0;
	}
}