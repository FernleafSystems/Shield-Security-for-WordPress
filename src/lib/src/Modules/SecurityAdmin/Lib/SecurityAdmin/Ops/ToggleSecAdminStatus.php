<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\SecurityAdmin\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class ToggleSecAdminStatus {

	use ModConsumer;

	public function turnOn() :bool {
		return $this->toggle( true );
	}

	public function turnOff() :bool {
		return $this->toggle( false );
	}

	private function toggle( bool $onOrOff ) :bool {
		$session = $this->getMod()->getSessionWP();
		if ( $session->valid ) {
			$this->getCon()
				 ->getModule_Sessions()
				 ->getSessionCon()
				 ->updateSessionParameter( 'secadmin_at', $onOrOff ? Services::Request()->ts() : 0 );
			$this->getCon()->this_req->is_security_admin = $onOrOff;
		}
		return (bool)$session->valid;
	}
}