<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Session;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement;
use FernleafSystems\Wordpress\Services\Services;

class RetrieveActive {

	use ModConsumer;

	/**
	 * @return int
	 */
	public function count() {
		return $this->getSelector()->count();
	}

	/**
	 * @return Session\EntryVO[]
	 */
	public function retrieve() {
		return $this->getSelector()->query();
	}

	/**
	 * @return Session\Select
	 */
	private function getSelector() {
		/** @var UserManagement\Options $oOpts */
		$oOpts = $this->getOptions();
		/** @var \ICWP_WPSF_FeatureHandler_UserManagement $oMod */
		$oMod = $this->getMod();

		/** @var Session\Select $oSel */
		$oSel = $oMod->getDbHandler_Sessions()->getQuerySelector();

		if ( $oOpts->hasMaxSessionTimeout() ) {
			$oSel->filterByLoginNotExpired( $this->getLoginExpiredBoundary() );
		}
		if ( $oOpts->hasSessionIdleTimeout() ) {
			$oSel->filterByLoginNotIdleExpired( $this->getLoginIdleExpiredBoundary() );
		}
		return $oSel;
	}

	/**
	 * @return int
	 */
	private function getLoginExpiredBoundary() {
		/** @var UserManagement\Options $oOpts */
		$oOpts = $this->getOptions();
		return Services::Request()->ts() - $oOpts->getMaxSessionTime();
	}

	/**
	 * @return int
	 */
	private function getLoginIdleExpiredBoundary() {
		/** @var UserManagement\Options $oOpts */
		$oOpts = $this->getOptions();
		return Services::Request()->ts() - $oOpts->getIdleTimeoutInterval();
	}
}