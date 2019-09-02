<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class ScanActionQuery {

	use Shield\Scans\Common\ScanActionConsumer;

	/**
	 * @param int $nExpiration
	 * @return bool
	 */
	public function isLockExpired( $nExpiration = 20 ) {
		$oFS = Services::WpFs();
		$sFile = $this->getActionStore()->getActionFilePath();
		return $oFS->exists( $sFile ) &&
			   ( Services::Request()->ts() - (int)$oFS->getFileContent( $sFile ) > $nExpiration );
	}

	/**
	 * @param int $nExpiration
	 * @return bool
	 */
	public function isScanExpired( $nExpiration = 60 ) {
		$bExpired = false;
		if ( $this->isRunning() ) {
			$aDef = $this->getActionStore()->readActionDefinitionFromDisk();
			if ( !empty( $aDef ) ) {
				$oAction = ( new BaseScanActionVO() )->applyFromArray( $aDef );
				$bExpired = Services::Request()->ts() - $oAction->created_at > $nExpiration;
			}
		}
		return $bExpired;
	}

	/**
	 * @return bool
	 */
	public function isRunning() {
		$sFile = $this->getActionStore()->getActionFilePath();
		return (bool)Services::WpFs()->exists( $sFile );
	}

	/**
	 * @return ActionStore
	 */
	protected function getActionStore() {
		return ( new ActionStore() )
			->setScanActionVO( $this->getScanActionVO() );
	}
}