<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class BuildScanAction extends Shield\Scans\Base\BaseBuildScanAction {

	protected function buildItems() {
		/** @var ScanActionVO $oAction */
		$oAction = $this->getScanActionVO();

		$oWpT = Services::WpThemes();
		$oTheme = $oWpT->getCurrent();
		$aThemes = [ $oTheme->get_stylesheet() ];
		if ( $oWpT->isActiveThemeAChild() ) {
			$aThemes[] = $oTheme->get_template();
		}

		$oAction->scan_items = array_merge(
			array_map(
				function ( $nKey ) {
					return 'plugins';
				},
				array_flip( Services::WpPlugins()->getActivePlugins() )
			),
			array_map(
				function ( $nKey ) {
					return 'themes';
				},
				array_flip( $aThemes )
			)
		);
	}

	protected function setCustomFields() {
		/** @var ScanActionVO $oAction */
		$oAction = $this->getScanActionVO();
		/** @var Shield\Modules\HackGuard\Options $oOpts */
		$oOpts = $this->getMod()->getOptions();
		$oAction->item_processing_limit = $oAction->is_async ? 3 : 0;
		$oAction->scan_depth = $oOpts->getPtgScanDepth();
		$oAction->file_exts = $oOpts->getPtgFileExtensions();
		$oAction->hashes_base_path = $oOpts->getPtgSnapsBaseDir();
	}
}