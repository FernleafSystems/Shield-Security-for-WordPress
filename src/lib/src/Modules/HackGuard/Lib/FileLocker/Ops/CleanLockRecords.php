<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class CleanLockRecords {

	use PluginControllerConsumer;

	public function run() {
		if ( self::con()->caps->hasCap( 'scan_file_locker' ) ) {
			foreach ( self::con()->comps->file_locker->getLocks() as $lock ) {
				if ( !\in_array( $lock->type, self::con()->comps->file_locker->getFilesToLock() ) ) {
					( new DeleteFileLock() )->delete( $lock );
				}
			}
		}
	}
}