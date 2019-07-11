<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\AsyncActions;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class MalScanLauncher extends Launcher {

	use Shield\Modules\ModConsumer;

	public function run() {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		/** @var Shield\Modules\HackGuard\Options $oOpts */
		$oOpts = $oMod->getOptions();

		$oAction = new MalScanActionVO();
		$oAction->id = 'malware_scan';
		$this->setAction( $oAction )
			 ->readAction();
		var_dump( $oAction );
		die();

		$oAction->ts_start = Services::Request()->ts();
		$oAction->files_map = ( new Shield\Scans\Mal\BuildFileMap() )
			->setWhitelistedPaths( $oOpts->getMalwareWhitelistPaths() )
			->build();

		$this->setAction( $oAction )
			 ->storeAction();
		var_dump( $oAction );
		die();
	}
}