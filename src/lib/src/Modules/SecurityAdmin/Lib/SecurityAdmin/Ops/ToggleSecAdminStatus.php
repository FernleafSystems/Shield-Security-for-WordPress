<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\SecurityAdmin\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Session\EntryVO;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\Session\Update;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\ModCon;

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
	 * @param bool $onOrOff
	 * @return bool
	 * @throws \Exception
	 */
	private function toggle( bool $onOrOff ) :bool {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$session = $this->getMod()->getSession();
		if ( !$session instanceof EntryVO ) {
			throw new \Exception( 'No session' );
		}
		/** @var Update $updater */
		$updater = $mod->getDbHandler_Sessions()->getQueryUpdater();
		return $onOrOff ? $updater->startSecurityAdmin( $session ) : $updater->terminateSecurityAdmin( $session );
	}
}