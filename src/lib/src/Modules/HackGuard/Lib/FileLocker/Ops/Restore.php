<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Services\Services;

class Restore extends BaseOps {

	public function run( Databases\FileLocker\EntryVO $record ) :bool {
		$reverted = Services::WpFs()->putFileContent(
			$record->file,
			( new ReadOriginalFileContent() )
				->setMod( $this->getMod() )
				->run( $record )
		);
		if ( $reverted ) {
			/** @var ModCon $mod */
			$mod = $this->getMod();
			/** @var Databases\FileLocker\Update $update */
			$update = $mod->getDbHandler_FileLocker()->getQueryUpdater();
			$update->markReverted( $record );
			$this->clearFileLocksCache();
		}
		return $reverted;
	}
}