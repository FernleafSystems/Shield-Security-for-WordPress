<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Exceptions\UnsupportedFileLockType;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Utility\FindLockRecordForFile;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class GetFileLocksToCreate {
	use PluginControllerConsumer;

	/**
	 * @return list<string>
	 */
	public function run(): array {
		$locksToCreate = [];
		$filesToLock = self::con()->comps->file_locker->getFilesToLock();
		if ( !empty( $filesToLock ) ) {

			foreach ( $filesToLock as $fileType ) {
				try {
					$lock = ( new FindLockRecordForFile() )
						->find( ( new BuildFileFromFileKey() )->build( $fileType ) );
					if ( empty( $lock ) ) {
						$locksToCreate[] = $fileType;
					}
				}
				catch ( UnsupportedFileLockType $e ) {
					$locksToCreate[] = $fileType;
				}
				catch ( \Exception $e ) {
				}
			}
		}
		return $locksToCreate;
	}
}
