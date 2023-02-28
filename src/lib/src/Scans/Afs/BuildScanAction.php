<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Options;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

class BuildScanAction extends Base\BuildScanAction {

	protected function buildScanItems() {
		$this->getScanActionVO()->items = ( new BuildScanItems() )->run();
	}

	protected function setCustomFields() {
		/** @var ScanActionVO $action */
		$action = $this->getScanActionVO();
		$action->file_exts = $this->getFileExts();
		$action->realtime_scan_last_at = $this->getScanController()->opts()->getLastRealtimeScanAt( true );
	}

	protected function getFileExts() :array {
		$scanCon = $this->getScanController();
		$ext = apply_filters( 'shield/scan_ptg_file_exts', $scanCon->getOptions()->getDef( 'file_scan_extensions' ) );
		return is_array( $ext ) ? $ext : $scanCon->getOptions()->getDef( 'file_scan_extensions' );
	}
}