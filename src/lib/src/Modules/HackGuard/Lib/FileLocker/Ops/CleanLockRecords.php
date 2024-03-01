<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModConsumer;

class CleanLockRecords {

	use ModConsumer;

	public function run() {
		if ( self::con()->caps->hasCap( 'scan_file_locker' ) ) {
			$FLcon = $this->mod()->getFileLocker();
			if ( \method_exists( $FLcon, 'getFilesToLock' ) ) {
				foreach ( $FLcon->getLocks() as $lock ) {
					if ( !\in_array( $lock->type, $FLcon->getFilesToLock() ) ) {
						( new DeleteFileLock() )->delete( $lock );
					}
				}
			}
		}
	}
}