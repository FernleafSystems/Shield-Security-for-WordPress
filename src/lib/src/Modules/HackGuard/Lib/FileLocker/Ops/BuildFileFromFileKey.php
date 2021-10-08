<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\File;
use FernleafSystems\Wordpress\Services\Services;

class BuildFileFromFileKey {

	/**
	 * @throws \Exception
	 */
	public function build( string $fileKey ) :File {
		$isSplitWpUrl = false; // TODO: is split URL?
		$maxPaths = 1;
		switch ( $fileKey ) {
			case 'wpconfig':
				$fileKey = 'wp-config.php';
				$maxPaths = 1;
				$levels = $isSplitWpUrl ? 3 : 2;
				$openBaseDir = ini_get( 'open_basedir' );
				if ( !empty( $openBaseDir ) ) {
					$levels--;
				}
				break;

			case 'root_htaccess':
				$fileKey = '.htaccess';
				$levels = $isSplitWpUrl ? 2 : 1;
				break;

			case 'root_webconfig':
				$fileKey = 'Web.Config';
				$levels = $isSplitWpUrl ? 2 : 1;
				break;

			case 'root_index':
				$fileKey = 'index.php';
				$levels = $isSplitWpUrl ? 2 : 1;
				break;
			default:
				if ( Services::WpFs()->isAbsPath( $fileKey ) && Services::WpFs()->isFile( $fileKey ) ) {
					$levels = 1;
					$maxPaths = 1;
				}
				else {
					throw new \Exception( 'Not a supported file lock type' );
				}
				break;
		}

		$file = new File( $fileKey );
		$file->max_levels = $levels;
		$file->max_paths = $maxPaths;
		return $file;
	}
}