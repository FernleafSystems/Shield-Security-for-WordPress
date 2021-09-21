<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg;

use FernleafSystems\Wordpress\Plugin\Shield;

class BuildScanAction extends Shield\Scans\Base\BaseBuildScanAction {

	protected function buildItems() {
		/** @var ScanActionVO $action */
		$action = $this->getScanActionVO();
		$action->items = ( new BuildFileMap() )
			->setMod( $this->getMod() )
			->setScanActionVO( $action )
			->build();
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