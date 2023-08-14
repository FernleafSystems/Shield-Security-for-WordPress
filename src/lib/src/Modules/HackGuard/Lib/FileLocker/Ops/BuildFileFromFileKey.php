<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Exceptions\UnsupportedFileLockType;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\File;

class BuildFileFromFileKey {

	/**
	 * @throws UnsupportedFileLockType
	 */
	public function build( string $fileType ) :File {
		$isSplitWpUrl = false; // TODO: is split URL?
		$maxPaths = 1;
		switch ( $fileType ) {
			case 'wpconfig':
				$fileName = 'wp-config.php';
				$maxPaths = 1;
				$levels = $isSplitWpUrl ? 3 : 2;
				$openBaseDir = \ini_get( 'open_basedir' );
				if ( !empty( $openBaseDir ) ) {
					$levels--;
				}
				break;

			case 'root_htaccess':
				$fileName = '.htaccess';
				$levels = $isSplitWpUrl ? 2 : 1;
				break;

			case 'root_webconfig':
				$fileName = 'Web.Config';
				$levels = $isSplitWpUrl ? 2 : 1;
				break;

			case 'root_index':
				$fileName = 'index.php';
				$levels = $isSplitWpUrl ? 2 : 1;
				break;
			default:
				throw new UnsupportedFileLockType( $fileType );
		}

		$file = new File( $fileType, $fileName );
		$file->max_levels = $levels;
		$file->max_paths = $maxPaths;
		return $file;
	}
}