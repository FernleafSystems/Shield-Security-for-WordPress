<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Session;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement;
use FernleafSystems\Wordpress\Services\Services;

class CleanExpired {

	use ModConsumer;

	public function run() {
		/** @var \ICWP_WPSF_FeatureHandler_UserManagement $mod */
		$mod = $this->getMod();
		/** @var UserManagement\Options $opts */
		$opts = $this->getOptions();
		/** @var Session\Delete $oTerminator */
		$oTerminator = $mod->getDbHandler_Sessions()->getQueryDeleter();

		// We use 14 as an outside case. If it's 2 days, WP cookie will expire anyway.
		// And if User Management is active, then it'll draw in that value.
		$oTerminator->forExpiredLoginAt( $this->getLoginExpiredBoundary() );

		// Default is ZERO, so we don't want to terminate all sessions if it's never set.
		if ( $opts->hasSessionIdleTimeout() ) {
			$oTerminator->forExpiredLoginIdle( $this->getLoginIdleExpiredBoundary() );
		}
	}

	private function getLoginExpiredBoundary() :int {
		/** @var UserManagement\Options $opts */
		$opts = $this->getOptions();
		return Services::Request()->ts() - $opts->getMaxSessionTime();
	}

	private function getLoginIdleExpiredBoundary() :int {
		/** @var UserManagement\Options $opts */
		$opts = $this->getOptions();
		return Services::Request()->ts() - $opts->getIdleTimeoutInterval();
	}
}