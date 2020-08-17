<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Sessions\Lib\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Session\Delete;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class Terminate {

	use ModConsumer;

	/**
	 * @return bool
	 */
	public function all() {
		/** @var \ICWP_WPSF_FeatureHandler_Sessions $mod */
		$mod = $this->getMod();
		return $mod->getDbHandler_Sessions()->tableDelete( true );
	}

	/**
	 * @param int $nId
	 * @return bool
	 */
	public function byRecordId( $nId ) {
		$this->getCon()->fireEvent( 'session_terminate' );
		return $this->getDeleter()->deleteById( (int)$nId );
	}

	/**
	 * @param string $sUsername
	 * @return bool
	 */
	public function byUsername( $sUsername ) {
		return $this->getDeleter()->forUsername( $sUsername ) !== false;
	}

	/**
	 * @return Delete
	 */
	private function getDeleter() {
		/** @var \ICWP_WPSF_FeatureHandler_Sessions $oMod */
		$oMod = $this->getMod();
		return $oMod->getDbHandler_Sessions()->getQueryDeleter();
	}
}