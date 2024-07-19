<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\FileLocker\Ops as FileLockerDB;
use FernleafSystems\Wordpress\Services\Services;

class Restore extends BaseOps {

	public function run( FileLockerDB\Record $record ) :bool {
		try {
			$reverted = Services::WpFs()->putFileContent(
				$record->path,
				( new ReadOriginalFileContent() )->run( $record )
			);
		}
		catch ( \Exception $e ) {
			$reverted = false;
		}

		if ( $reverted ) {
			/** @var FileLockerDB\Update $update */
			$update = self::con()->db_con->file_locker->getQueryUpdater();
			$update->markReverted( $record );
			self::con()->comps->file_locker->clearLocks();
		}

		return $reverted;
	}
}