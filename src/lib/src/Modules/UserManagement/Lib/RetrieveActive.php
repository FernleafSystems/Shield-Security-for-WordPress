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
		/** @var UserManagement\Options $opt */
		$opt = $this->getOptions();

		/** @var Session\Select $oSel */
		$oSel = $this->getMod()->getDbHandler_Sessions()->getQuerySelector();

		if ( $opt->hasMaxSessionTimeout() ) {
			$oSel->filterByLoginNotExpired( $this->getLoginExpiredBoundary() );
		}
		if ( $opt->hasSessionIdleTimeout() ) {
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