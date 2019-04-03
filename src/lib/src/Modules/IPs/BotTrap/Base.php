<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\BotTrap;

use FernleafSystems\Wordpress\Plugin\Shield;

abstract class Base {

	use Shield\AuditTrail\Auditor,
		Shield\Modules\ModConsumer;
	const OPT_KEY = '';

	public function run() {
		$this->process();
	}

	protected function doTransgression() {
		/** @var \ICWP_WPSF_FeatureHandler_Ips $oFO */
		$oFO = $this->getMod();
		if ( $this->isTransgression() ) {
			$oFO->setIpTransgressed( $this->isDoubleTransgression() ? 2 : 1 );
		}
		else {
			$oFO->setIpBlocked();
		}
		$this->writeAudit();
	}

	abstract protected function process();

	/**
	 * @return bool
	 */
	abstract protected function isTransgression();

	/**
	 * @return bool
	 */
	protected function isDoubleTransgression() {
		/** @var \ICWP_WPSF_FeatureHandler_Ips $oFO */
		$oFO = $this->getMod();
		return $oFO->isSelectOptionDoubleTransgression( static::OPT_KEY );
	}

	/**
	 * @return $this
	 */
	abstract protected function writeAudit();
}
