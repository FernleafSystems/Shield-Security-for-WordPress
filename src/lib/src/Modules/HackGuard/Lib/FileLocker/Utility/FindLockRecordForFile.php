<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Utility;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\FileLocker\Ops as FileLockerDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\File;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops\LoadFileLocks;

class FindLockRecordForFile {

	public function find( File $file ) :?FileLockerDB\Record {
		$theLock = null;
		foreach ( $file->getPossiblePaths() as $path ) {
			foreach ( ( new LoadFileLocks() )->ofType( $file->type ) as $maybeLock ) {
				if ( $maybeLock->path === $path ) {
					$theLock = $maybeLock;
					break;
				}
			}
		}
		return $theLock;
	}
}
