<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\FileLocker;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class Accept
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops
 */
class Accept extends BaseOps {

	/**
	 * @param FileLocker\EntryVO $oLock
	 */
	public function run( $oLock ) {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		/** @var FileLocker\Handler $oDbH */
		$oDbH = $oMod->getDbHandler_FileLocker();

		/** @var FileLocker\Update $oUpdater */
		$oUpdater = $oDbH->getQueryUpdater();
		$oUpdater->updateOriginalHash( $oLock, hash_file( 'sha1', $oLock->file ) );

		$this->clearFileLocksCache();
	}
}