<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Session;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement;
use FernleafSystems\Wordpress\Services\Services;

class CleanExpired {

	use ModConsumer;

	public function run() {
		/** @var UserManagement\Options $opts */
		$opts = $this->getOptions();
		/** @var Session\Delete $terminator */
		$terminator = $this->getCon()
						   ->getModule_Sessions()
						   ->getDbHandler_Sessions()
						   ->getQueryDeleter();

		// We use 14 as an outside case. If it's 2 days, WP cookie will expire anyway.
		// And if User Management is active, then it'll draw in that value.
		$terminator->forExpiredLoginAt( $this->getLoginExpiredBoundary() );

		// Default is ZERO, so we don't want to terminate all sessions if it's never set.
		if ( $opts->hasSessionIdleTimeout() ) {
			$terminator->forExpiredLoginIdle( $this->getLoginIdleExpiredBoundary() );
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