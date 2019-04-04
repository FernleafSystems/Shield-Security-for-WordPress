<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\BotTrack;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

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
	protected function isTransgression() {
		/** @var \ICWP_WPSF_FeatureHandler_Ips $oFO */
		$oFO = $this->getMod();
		return $oFO->isTrackOptTransgression( static::OPT_KEY );
	}

	/**
	 * @return bool
	 */
	protected function isDoubleTransgression() {
		/** @var \ICWP_WPSF_FeatureHandler_Ips $oFO */
		$oFO = $this->getMod();
		return $oFO->isTrackOptDoubleTransgression( static::OPT_KEY );
	}

	/**
	 * @return $this
	 */
	protected function writeAudit() {
		$this->createNewAudit( 'wpsf', $this->getAuditMsg(), 2, 'bot'.static::OPT_KEY );
		return $this;
	}

	/**
	 * @return $this
	 */
	abstract protected function getAuditMsg();
}
