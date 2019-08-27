<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

abstract class ICWP_WPSF_Processor_BaseDb extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 * @return bool
	 */
	public function isReadyToRun() {
		try {
			$oHandler = $this->getMod()->getDbHandler();
			$bReady = parent::isReadyToRun()
					  && ( $oHandler instanceof Shield\Databases\Base\Handler )
					  && $oHandler->isReady();
		}
		catch ( \Exception $oE ) {
			$bReady = false;
		}
		return $bReady;
	}
}