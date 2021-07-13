<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class Restore
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops
 */
class Restore extends BaseOps {

	public function run( Databases\FileLocker\EntryVO $record ) :bool {
		$bReverted = Services::WpFs()->putFileContent(
			$record->file,
			( new ReadOriginalFileContent() )
				->setMod( $this->getMod() )
				->run( $record )
		);
		if ( $bReverted ) {
			/** @var ModCon $mod */
			$mod = $this->getMod();
			/** @var Databases\FileLocker\Update $update */
			$update = $mod->getDbHandler_FileLocker()->getQueryUpdater();
			$update->markReverted( $record );
			$this->clearFileLocksCache();
		}
		return $bReverted;
	}
}