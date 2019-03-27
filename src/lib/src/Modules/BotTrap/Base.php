<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\BotTrap;

use FernleafSystems\Wordpress\Plugin\Shield;

abstract class Base {

	use Shield\AuditTrail\Auditor,
		Shield\Modules\ModConsumer;

	public function run() {
		$this->process();
	}

	protected function doTransgression() {
		/** @var \ICWP_WPSF_FeatureHandler_Bottrap $oFO */
		$oFO = $this->getMod();
		$this->isTransgression() ? $oFO->setIpTransgressed() : $oFO->setIpBlocked();
		$this->writeAudit();
	}

	abstract protected function process();

	/**
	 * @return bool
	 */
	abstract protected function isTransgression();

	/**
	 * @return $this
	 */
	abstract protected function writeAudit();
}
