<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class BuildScanAction extends Shield\Scans\Base\BaseBuildScanAction {

	protected function setCustomFields() {
		/** @var ScanActionVO $oAction */
		$oAction = $this->getScanActionVO();
		$oAction->scan_items = array_merge(
			array_map(
				function ( $nKey ) {
					return 'plugin';
				},
				array_flip( Services::WpPlugins()->getInstalledPluginFiles() )
			),
			array_map(
				function ( $nKey ) {
					return 'theme';
				},
				array_flip(
					array_map(
						function ( $oT ) {
							/** @var \WP_Theme $oT */
							return $oT->get_stylesheet();
						},
						Services::WpThemes()->getThemes()
					)
				)
			)
		);
		$oAction->total_scan_items = count( $oAction->scan_items );
		$oAction->item_processing_limit = $oAction->is_async ? 3 : 0;
	}
}