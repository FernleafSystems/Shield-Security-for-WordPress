<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\BaseBuildFileMap;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers\StandardDirectoryIterator;
use FernleafSystems\Wordpress\Services\Services;

class BuildFileMap extends BaseBuildFileMap {

	/**
	 * @return string[]
	 */
	public function build() :array {
		$files = [];

		/** @var ScanActionVO $action */
		$action = $this->getScanActionVO();

		$abspath = wp_normalize_path( ABSPATH );
		foreach ( $this->getScanRoots() as $dir ) {
			try {
				foreach ( StandardDirectoryIterator::create( $dir, 0, $action->file_exts ) as $item ) {
					/** @var \SplFileInfo $item */
					try {
						if ( !$this->isAutoFilterFile( $item ) ) {
							$files[] = str_replace( $abspath, '', wp_normalize_path( $item->getPathname() ) );
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