<?php

use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\Events;

class ICWP_WPSF_Processor_Events extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 * @var bool
	 */
	private $bStat = false;

	public function run() {
		$this->bStat = true;
	}

	public function onModuleShutdown() {
		parent::onModuleShutdown();
		if ( $this->bStat && !$this->getCon()->isPluginDeleting() ) {
			$this->commitEvents();
		}
	}

	/**
	 */
	private function commitEvents() {
		/** @var \ICWP_WPSF_FeatureHandler_Events $oMod */
		$oMod = $this->getMod();
		/** @var Events\Handler $oDbh */
		$oDbh = $oMod->getDbHandler();
		$oDbh->commitEvents( $oMod->getRegisteredEvents( true ) );
	}
}