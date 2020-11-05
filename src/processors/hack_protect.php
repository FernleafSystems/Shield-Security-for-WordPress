<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

class ICWP_WPSF_Processor_HackProtect extends Modules\BaseShield\ShieldProcessor {

	public function run() {
		/** @var HackGuard\ModCon $mod */
		$mod = $this->getMod();

		$this->getSubProScanner()->execute();

		/** @var HackGuard\Options $opts */
		$opts = $this->getOptions();
		if ( count( $opts->getFilesToLock() ) > 0 ) {
			$mod->getFileLocker()->execute();
		}
	}

	public function getSubProScanner() :\ICWP_WPSF_Processor_HackProtect_Scanner {
		return $this->getSubPro( 'scanner' );
	}

	protected function getSubProMap() :array {
		return [
			'scanner' => 'ICWP_WPSF_Processor_HackProtect_Scanner',
		];
	}
}