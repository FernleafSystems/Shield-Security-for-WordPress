<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker;

class FileLockerController {

	use ModConsumer;

	public function run() {
		add_action( $this->getCon()->prefix( 'plugin_shutdown' ), function () {
			if ( $this->getOptions()->isOptChanged( 'file_locker' ) ) {
				$this->deleteAllLocks();
			}
			else {
				$this->runAnalysis();
			}
		} );
	}

	public function deleteAllLocks() {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		$oMod->getDbHandler_FileLocker()->deleteTable( true );
	}

	private function runAnalysis() {
		/** @var HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();

		// 1. First assess the existing locks for changes.
		( new Ops\AssessAndRepairFileLocks() )
			->setMod( $this->getMod() )
			->run();

		// 2. Create new file locks as required
		foreach ( $oOpts->getFilesToLock() as $sFileKey ) {
			try {
				( new Ops\CreateFileLock() )
					->setMod( $this->getMod() )
					->setWorkingFile( $this->getFile( $sFileKey ) )
					->create();
			}
			catch ( \Exception $oE ) {
			}
		}
	}

	/**
	 * @param string $sFileKey
	 * @return File|null
	 * @throws \Exception
	 */
	private function getFile( $sFileKey ) {
		$oFile = null;

		switch ( $sFileKey ) {
			case 'wpconfig':
				$sFileKey = 'wp-config.php';
				$nLevels = 2;
				// TODO: is split URL?
				break;
			case 'root_htaccess':
				$sFileKey = '.htaccess';
				$nLevels = 2;
				break;
			case 'root_index':
				$sFileKey = 'index.php';
				$nLevels = 2;
				break;
			default:
				throw new \Exception( 'not currently support file lock type' );
				break;
		}
		$oFile = new FileLocker\File( $sFileKey );
		$oFile->max_levels = $nLevels;
		return $oFile;
	}
}
