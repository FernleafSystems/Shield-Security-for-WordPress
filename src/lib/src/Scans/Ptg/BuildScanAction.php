<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg;

use FernleafSystems\Wordpress\Plugin\Shield;

class BuildScanAction extends Shield\Scans\Base\BaseBuildScanAction {

	protected function buildItems() {
		/** @var ScanActionVO $oAction */
		$oAction = $this->getScanActionVO();
		$oAction->items = ( new BuildFileMap() )
			->setScanActionVO( $oAction )
			->build();
	}

	protected function setCustomFields() {
		/** @var ScanActionVO $oAction */
		$oAction = $this->getScanActionVO();
		$oAction->file_exts = $this->getFileExts();
	}

	/**
	 * @return array
	 */
	private function getFileExts() {
		$aFileExts = apply_filters(
			$this->getCon()->prefix( 'scan_ptg_file_exts' ),
			[ 'js', 'json', 'otf', 'svg', 'ttf', 'eot', 'woff', 'woff2', 'php', 'php5', 'php7', 'phtml' ]
		);
		return is_array( $aFileExts ) ? $aFileExts : [];
	}
}