<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker;
use FernleafSystems\Wordpress\Services\Services;

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
		( new Ops\AssessLocks() )
			->setMod( $this->getMod() )
			->run();

		// 2. Create new file locks as required
		foreach ( $oOpts->getFilesToLock() as $sFileKey ) {
			try {
				( new Ops\CreateFileLocks() )
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

		$bIsSplitWp = false;
		$nMaxPaths = 0;
		switch ( $sFileKey ) {
			case 'wpconfig':
				$sFileKey = 'wp-config.php';
				$nLevels = $bIsSplitWp ? 3 : 2;
				$nMaxPaths = 1;
				// TODO: is split URL?
				break;
			case 'root_htaccess':
				$sFileKey = '.htaccess';
				$nLevels = $bIsSplitWp ? 2 : 1;
				break;
			case 'root_index':
				$sFileKey = 'index.php';
				$nLevels = $bIsSplitWp ? 2 : 1;
				break;
			default:
				if ( path_is_absolute( $sFileKey ) && Services::WpFs()->isFile( $sFileKey ) ) {
					$nLevels = 1;
					$nMaxPaths = 1;
				}
				else {
					throw new \Exception( 'Not a supported file lock type' );
				}
				break;
		}
		$oFile = new FileLocker\File( $sFileKey );
		$oFile->max_levels = $nLevels;
		$oFile->max_paths = $nMaxPaths;
		return $oFile;
	}
}
