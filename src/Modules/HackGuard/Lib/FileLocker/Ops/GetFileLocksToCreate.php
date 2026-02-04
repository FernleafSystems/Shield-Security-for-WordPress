<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Utility\FindLockRecordForFile;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class GetFileLocksToCreate {

	use PluginControllerConsumer;

	public function run() :array {
		$locksToCreate = [];
		foreach ( self::con()->comps->file_locker->getFilesToLock() as $fileType ) {
			try {
				$lock = ( new FindLockRecordForFile() )
					->find( ( new BuildFileFromFileKey() )->build( $fileType ) );
				if ( empty( $lock ) ) {
					$locksToCreate[] = $fileType;
				}
			}
			catch ( \Exception $e ) {
			}
		}
		return $locksToCreate;
	}
}