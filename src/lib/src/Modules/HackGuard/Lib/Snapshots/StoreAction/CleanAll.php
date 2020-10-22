<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers\StandardDirectoryIterator;
use FernleafSystems\Wordpress\Services\Services;

class CleanAll extends BaseBulk {

	public function run() {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		try {
			$nBoundary = Services::Request()
								 ->carbon()
								 ->subDay()->timestamp;
			$oDirIt = StandardDirectoryIterator::create( $oMod->getPtgSnapsBaseDir() );
			foreach ( $oDirIt as $oFile ) {
				/** @var \SplFileInfo $oFile */
				if ( $nBoundary > $oFile->getMTime() ) {
					Services::WpFs()->deleteFile( $oFile->getPathname() );
				}
			}
		}
		catch ( \Exception $oE ) {
		}
	}
}