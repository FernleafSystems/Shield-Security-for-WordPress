<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Utility;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\HashesStorageDir;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class CleanOutOldGuardFiles {

	use ExecOnce;
	use PluginControllerConsumer;

	protected function run( int $limit = 50 ) {
		$FS = Services::WpFs();

		$firstAcceptableDir = null;
		$count = 0;
		$root = self::con()->cache_dir_handler->dir();
		if ( !empty( $root ) ) {
			foreach ( $FS->getAllFilesInDir( $root ) as $fileItem ) {
				if ( $FS->isDir( $fileItem ) ) {
					$dirBase = \basename( $fileItem );
					if ( $dirBase === 'ptguard' ) {
						$FS->deleteDir( $fileItem );
					}
					elseif ( \preg_match( sprintf( '#^ptguard-[a-z0-9]{%s}$#i', HashesStorageDir::SUFFIX_LENGTH ), $dirBase ) ) {
						if ( empty( $firstAcceptableDir ) ) {
							$firstAcceptableDir = $fileItem;
						}
						else {
							$count++;
							$FS->deleteDir( $fileItem );
						}
					}
				}

				if ( $count > $limit ) {
					break;
				}
			}
		}
	}
}