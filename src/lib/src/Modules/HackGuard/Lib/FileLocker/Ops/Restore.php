<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
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
			/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
			$oMod = $this->getMod();
			/** @var Databases\FileLocker\Handler $oDbH */
			$oDbH = $oMod->getDbHandler_FileLocker();
			/** @var Databases\FileLocker\Update $oUpd */
			$oUpd = $oDbH->getQueryUpdater();
			$oUpd->markReverted( $oRecord );
			$this->clearFileLocksCache();
		}
		return $bReverted;
	}
}