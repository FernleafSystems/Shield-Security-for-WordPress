<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\SecurityAdmin\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class ToggleSecAdminStatus {

	use ModConsumer;

	public function turnOn() :bool {
		try {
			$success = $this->toggle( true );
		}
		catch ( \Exception $e ) {
			$success = false;
		}
		return $success;
	}

	public function turnOff() :bool {
		try {
			$success = $this->toggle( false );
		}
		catch ( \Exception $e ) {
			$success = false;
		}
		return $success;
	}

	/**
	 * @throws \Exception
	 */
	private function toggle( bool $onOrOff ) :bool {
		$session = $this->getMod()->getSession();
		if ( empty( $session ) ) {
			throw new \Exception( 'No session' );
		}

		$this->getCon()
			 ->getModule_Sessions()
			 ->getSessionCon()
			 ->updateSessionParameter( 'secadmin_at', $onOrOff ? Services::Request()->ts() : 0 );
		return true;
	}
}