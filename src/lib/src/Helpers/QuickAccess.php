<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Helpers;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;

class QuickAccess {

	public static function IsRequestWhiteListed() {
		try {
			return Controller::GetInstance()
							 ->getModule_IPs()
							 ->isVisitorWhitelisted();
		}
		catch ( \Exception $e ) {
			return false;
		}
	}
}
