<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\FileLocker\Ops as FileLockerDB;
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
			$update = $this->mod()->getDbH_FileLocker()->getQueryUpdater();
			$update->markReverted( $record );
			$this->clearFileLocksCache();
		}

		return $reverted;
	}
}