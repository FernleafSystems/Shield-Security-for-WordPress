<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Scans;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\ScanActionVO;

abstract class BasePluginThemeFile extends BaseScan {

	protected function isSupportedFileExt() :bool {
		/** @var ScanActionVO $action */
		$action = $this->getScanActionVO();
		$ext = strtolower( (string)pathinfo( $this->pathFull, PATHINFO_EXTENSION ) );
		return !empty( $ext ) && in_array( $ext, $action->file_exts );
	}
}