<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

class BuildScanAction extends Base\BuildScanAction {

	protected function buildItems() {
		$this->getScanActionVO()->items = ( new BuildScanItems() )
			->setMod( $this->getScanController()->getMod() )
			->setScanActionVO( $this->getScanActionVO() )
			->run();
	}

	protected function setCustomFields() {
		/** @var ScanActionVO $action */
		$action = $this->getScanActionVO();
		$action->file_exts = $this->getFileExts();
	}

	private function getFileExts() :array {
		$ext = apply_filters( 'shield/scan_ptg_file_exts', $this->getOptions()->getDef( 'file_scan_extensions' ) );
		return is_array( $ext ) ? $ext : $this->getOptions()->getDef( 'file_scan_extensions' );
	}
}