<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ufc;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Options;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\BaseBuildFileMap;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Common\ScanActionConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers\StandardDirectoryIterator;
use FernleafSystems\Wordpress\Services\Services;

class BuildScanItems extends BaseBuildFileMap {

	/**
	 * @return string[]
	 */
	public function build() :array {
		$files = [];
		$coreHashes = Services::CoreFileHashes();
		if ( !$coreHashes->isReady() ) {
			return $files;
		}

		/** @var ScanActionVO $action */
		$action = $this->getScanActionVO();

		foreach ( $action->scan_dirs as $dir => $fileExts ) {
			try {
				/**
				 * The filter handles the bulk of the file inclusions and exclusions
				 * We can set the types (extensions) of the files to include
				 * useful for the upload directory where we're only interested in JS and PHP
				 * The filter will also be responsible (in this case) for filtering out
				 * WP Core files from the collection of files to be assessed
				 */
				foreach ( StandardDirectoryIterator::create( $dir, 0, $fileExts, true ) as $file ) {
					/** @var \SplFileInfo $file */
					$path = wp_normalize_path( $file->getPathname() );
					if ( !$coreHashes->isCoreFile( $path ) && !$this->isWhitelistedPath( $path ) && !$this->isAutoFilterFile( $file ) ) {
						$files[] = wp_normalize_path( $path );
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
}