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
//		var_dump( $oAction );
//		die();

		$oRs = new Shield\Scans\Mal\ResultsSet();

		$nStart = 0;
		$nSliceLength = 100;
		do {
			$aSlice = array_slice( $oAction->files_map, $nStart, $nSliceLength );
			$oTempRs = ( new Shield\Scans\Mal\ScannerFromFileMap() )
				->setFileMap( $aSlice )
				->setMalSigsRegex( $oOpts->getMalSignaturesRegex() )
				->setMalSigsSimple( $oOpts->getMalSignaturesSimple() )
				->run();

			( new Shield\Scans\Helpers\CopyResultsSets() )->copyTo( $oTempRs, $oRs );
			$nStart += $nSliceLength;
		} while ( $nStart < count( $oAction->files_map ) );

//		$oResult = ( new Shield\Scans\Mal\ScannerFromFileMap() )
//			->setFileMap( $oAction->files_map )
//			->setMalSigsRegex( $oOpts->getMalSignaturesRegex() )
//			->setMalSigsSimple( $oOpts->getMalSignaturesSimple() )
//			->run();
		var_dump( $oRs );
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