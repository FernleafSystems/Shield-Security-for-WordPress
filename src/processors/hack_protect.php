<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

class ICWP_WPSF_Processor_HackProtect extends Modules\BaseShield\ShieldProcessor {

	public function run() {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();

		$this->getSubProScanner()->execute();

		/** @var HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		if ( count( $oOpts->getFilesToLock() ) > 0 ) {
			$oMod->getFileLocker()->execute();
		}
	}

	/**
	 * @return \ICWP_WPSF_Processor_HackProtect_Scanner|mixed
	 */
	public function getSubProScanner() {
		return $this->getSubPro( 'scanner' );
	}

	/**
	 * @return array
	 */
	protected function getSubProMap() {
		return [
			'scanner' => 'ICWP_WPSF_Processor_HackProtect_Scanner',
		];
	}
}