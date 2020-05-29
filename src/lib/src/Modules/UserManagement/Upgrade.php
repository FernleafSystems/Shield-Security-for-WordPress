<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Upgrade extends Base\Upgrade {

	protected function upgradeModule($sCurrentBuild) {
		if (version_compare($sCurrentBuild, '')) {
		}
		$this->upgrade_903();
	}

	private function upgrade_903() {
		/** @var Options $oOpts */
		$oOpts = $this->getOptions();

		$aChecks = $oOpts->getEmailValidationChecks();
		if ( in_array( 'domain', $aChecks ) ) {
			$aChecks[] = 'domain_registered';
			unset( $aChecks[ array_search( 'domain', $aChecks ) ] );
			$oOpts->setOpt( 'email_checks', $aChecks );
		}
	}
}