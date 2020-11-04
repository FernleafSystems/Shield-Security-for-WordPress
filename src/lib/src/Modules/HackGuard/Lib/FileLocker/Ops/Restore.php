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

	/**
	 * @param Databases\FileLocker\EntryVO $oRecord
	 * @return mixed
	 */
	public function run( $oRecord ) {
		$bReverted = Services::WpFs()->putFileContent(
			$oRecord->file,
			( new ReadOriginalFileContent() )
				->setMod( $this->getMod() )
				->run( $oRecord )
		);
		if ( $bReverted ) {
			/** @var ModCon $mod */
			$mod = $this->getMod();
			/** @var Databases\FileLocker\Update $oUpd */
			$oUpd = $mod->getDbHandler_FileLocker()->getQueryUpdater();
			$oUpd->markReverted( $oRecord );
			$this->clearFileLocksCache();
		}
		return $bReverted;
	}
}