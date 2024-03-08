<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs;

class BuildScanAction extends \FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\BuildScanAction {

	protected function buildScanItems() {
		$this->getScanActionVO()->items = ( new BuildScanItems() )->run();
	}

	protected function setCustomFields() {
		/** @var ScanActionVO $action */
		$action = $this->getScanActionVO();
		$action->file_exts = $this->getFileExts();
	}

	protected function getFileExts() :array {
		$def = self::con()->cfg->configuration->def( 'file_scan_extensions' );
		$ext = apply_filters( 'shield/scan_ptg_file_exts', $def );
		return \is_array( $ext ) ? $ext : $def;
	}
}