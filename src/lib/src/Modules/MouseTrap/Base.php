<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\MouseTrap;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

abstract class Base {

	use Shield\AuditTrail\Auditor,
		Shield\Modules\ModConsumer;

	public function run() {
		add_action( 'init', [ $this, 'onWpInit' ] );
	}

	public function onWpInit() {
		if ( !Services::WpUsers()->isUserLoggedIn() ) {
			$this->process();
		}
	}

	protected function process() {
	}

	protected function doTransgression() {
		/** @var \ICWP_WPSF_FeatureHandler_Mousetrap $oFO */
		$oFO = $this->getMod();
		if ( !$oFO->isVerifiedBot() ) {
			$this->isTransgression() ? $oFO->setIpTransgressed() : $oFO->setIpBlocked();
			$this->writeAudit();
		}
	}

	/**
	 * @return bool
	 */
	abstract protected function isTransgression();

	/**
	 * @return $this
	 */
	abstract protected function writeAudit();
}
