<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Exceptions\UnsupportedFileLockType;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\File;

class BuildFileFromFileKey {

	/**
	 * @throws UnsupportedFileLockType
	 */
	public function build( string $fileType ) :File {
		// TODO: $isSplitWpUrl = false;
		$maxPaths = 1;
		$dir = ABSPATH;
		switch ( $fileType ) {
			case 'wpconfig':
				$fileName = 'wp-config.php';
				$levels = 2; // $isSplitWpUrl ? 3 : 2;
				$openBaseDir = \ini_get( 'open_basedir' );
				if ( !empty( $openBaseDir ) ) {
					$levels--;
				}
				break;

			case 'root_htaccess':
				$fileName = '.htaccess';
				$levels = 1; // $isSplitWpUrl ? 2 : 1;
				break;

			case 'theme_functions':
				$fileName = 'functions.php';
				$dir = get_stylesheet_directory();
				$levels = 1;
				break;

			case 'root_webconfig':
				$fileName = 'Web.Config';
				$levels = 1; // $isSplitWpUrl ? 2 : 1;
				break;

			case 'root_index':
				$fileName = 'index.php';
				$levels = 1; // $isSplitWpUrl ? 2 : 1;
				break;
			default:
				throw new UnsupportedFileLockType( $fileType );
		}

		$file = new File( $fileType, $fileName, $dir );
		$file->max_levels = $levels;
		$file->max_paths = $maxPaths;
		return $file;
	}
}