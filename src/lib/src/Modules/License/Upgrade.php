<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Upgrade extends Base\Upgrade {

	protected function upgrade_1604() {
		$con = $this->getCon();
		if ( $con->isPremiumActive() ) {
			try {
				$con->getModule_License()
					->getLicenseHandler()
					->verify( false, true );
			}
			catch ( \Exception $e ) {
			}
		}
	}

	protected function upgrade_1612() {
		$con = $this->getCon();
		if ( $con->isPremiumActive() ) {
			try {
				$con->getModule_License()
					->getLicenseHandler()
					->verify( false, true );
			}
			catch ( \Exception $e ) {
			}
		}
	}
}