<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Helpers;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;

class QuickAccess {

	public static function IsRequestWhiteListed() :bool {
		try {
			return Controller::GetInstance()->req->is_ip_whitelisted;
		}
		catch ( \Exception $e ) {
			return false;
		}
	}
}
