<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

/**
 * Class ScannerProfileConsumer
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Base
 */
trait ScannerProfileConsumer {

	/**
	 * @var ScannerProfile
	 */
	private $oScannerProfile;

	/**
	 * @return ScannerProfile
	 */
	public function getScannerProfile() {
		if ( !$this->oScannerProfile instanceof ScannerProfile) {
			$this->oScannerProfile = new ScannerProfile();
		}
		return $this->oScannerProfile;
	}

	/**
	 * @param ScannerProfile $oScannerProfile
	 * @return $this
	 */
	public function setScannerProfile( $oScannerProfile ) {
		$this->oScannerProfile = $oScannerProfile;
		return $this;
	}

}