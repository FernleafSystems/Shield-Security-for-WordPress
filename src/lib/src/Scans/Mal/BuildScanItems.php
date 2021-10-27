<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\BaseBuildFileMap;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers\StandardDirectoryIterator;

class BuildScanItems extends BaseBuildFileMap {

	/**
	 * @return string[]
	 */
	public function build() :array {
		$files = [];
		$this->preBuild();
		return $files;
	}

	protected function preBuild() {
		/** @var ScanActionVO $action */
		$action = $this->getScanActionVO();

		if ( empty( $action->scan_root_dirs ) || !is_array( $action->scan_root_dirs ) ) {
			$action->scan_root_dirs = [
				ABSPATH                          => 1,
				path_join( ABSPATH, WPINC )      => 0,
				path_join( ABSPATH, 'wp-admin' ) => 0,
				WP_CONTENT_DIR                   => 0,
			];
		}
		if ( empty( $action->file_exts ) ) {
			$action->file_exts = [ 'php', 'php5' ];
		}
		if ( !is_array( $action->paths_whitelisted ) ) {
			$action->paths_whitelisted = [];
		}
	}
}