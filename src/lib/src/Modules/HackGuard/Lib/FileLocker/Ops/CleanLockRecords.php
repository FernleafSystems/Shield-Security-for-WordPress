<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModConsumer;

class CleanLockRecords {

	use ModConsumer;

	public function run() {
		foreach ( $this->mod()->getFileLocker()->getLocks() as $lock ) {
			if ( !\in_array( $lock->type, $this->opts()->getFilesToLock() ) ) {
				( new DeleteFileLock() )->delete( $lock );
			}
		}
	}
}