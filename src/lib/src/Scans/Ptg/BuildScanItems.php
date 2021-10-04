<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\BaseBuildFileMap;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers\StandardDirectoryIterator;
use FernleafSystems\Wordpress\Services\Services;

class BuildScanItems extends BaseBuildFileMap {

	/**
	 * @return string[]
	 */
	public function build() :array {
		$files = [];

		/** @var ScanActionVO $action */
		$action = $this->getScanActionVO();

		foreach ( $this->getScanRoots() as $dir ) {
			try {
				foreach ( StandardDirectoryIterator::create( $dir, 0, $action->file_exts ) as $item ) {
					/** @var \SplFileInfo $item */
					$path = wp_normalize_path( $item->getPathname() );
					try {
						if ( !$this->isWhitelistedPath( $path ) && !$this->isAutoFilterFile( $item ) ) {
							$files[] = $path;
						}
					}
					catch ( \Exception $e ) {
					}
				}
			}
			catch ( \Exception $e ) {
				error_log(
					sprintf( 'Shield file scanner (%s) attempted to read directory (%s) but there was error: "%s".',
						$action->scan, $dir, $e->getMessage() )
				);
			}
		}

		return $files;
	}

	private function getScanRoots() :array {
		$roots = [];

		$WPP = Services::WpPlugins();
		foreach ( $WPP->getPluginsAsVo() as $plugin ) {
			if ( $plugin->active ) {
				$roots[] = $plugin->getInstallDir();
			}
		}

		$WPT = Services::WpThemes();
		$current = $WPT->getCurrent();
		$roots[] = $current->get_stylesheet_directory();
		if ( $WPT->isActiveThemeAChild() ) {
			$roots[] = $current->get_template_directory();
		}

		return $roots;
	}
}