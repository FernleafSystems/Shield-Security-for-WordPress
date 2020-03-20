<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class Revert
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\WpConfig
 */
class Revert extends BaseOps {

	/**
	 * @param Databases\FileLocker\EntryVO $oRecord
	 * @return mixed
	 */
	public function run( $oRecord ) {
		$bReverted = Services::WpFs()->putFileContent(
			$oRecord->file, ( new ReadOriginalFileContent() )->run( $oRecord )
		);
		if ( $bReverted ) {
			/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
			$oMod = $this->getMod();
			/** @var Databases\FileLocker\Handler $oDbH */
			$oDbH = $oMod->getDbHandler_FileLocker();
			/** @var Databases\FileLocker\Update $oUpd */
			$oUpd = $oDbH->getQueryUpdater();
			$oUpd->markReverted( $oRecord );
		}
		return $bReverted;
	}
}