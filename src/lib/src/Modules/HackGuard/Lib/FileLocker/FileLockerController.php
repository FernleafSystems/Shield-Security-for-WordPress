<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\FileLocker;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
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

	/**
	 * @return int
	 */
	public function countProblems() {
		return count( ( new Ops\LoadFileLocks() )
			->setMod( $this->getMod() )
			->withProblems() );
	}

	public function deleteAllLocks() {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		$oMod->getDbHandler_FileLocker()->deleteTable( true );
	}

	/**
	 * @param $nID
	 * @return FileLocker\EntryVO|null
	 */
	public function getFileLock( $nID ) {
		$aLocks = ( new Ops\LoadFileLocks() )
			->setMod( $this->getMod() )
			->loadLocks();
		return isset( $aLocks[ $nID ] ) ? $aLocks[ $nID ] : null;
	}

	private function runAnalysis() {
		/** @var HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();

		// 1. First assess the existing locks for changes.
		$aProblemIds = ( new Ops\AssessLocks() )
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

		$this->runProblemNotifications( $aProblemIds );
	}

	/**
	 * @param int[] $aProblemIds
	 */
	protected function runProblemNotifications( $aProblemIds ) {
		if ( !empty( $aProblemIds ) ) {
			/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
			$oMod = $this->getMod();
			/** @var FileLocker\Handler $oDbH */
			$oDbH = $oMod->getDbHandler_FileLocker();

			$aToNotify = [];
			foreach ( $aProblemIds as $nKey => $nID ) {
				$oLock = $this->getFileLock( $nID );
				if ( Services::Request()->carbon()->subWeek()->timestamp > $oLock->notified_at ) {
					$aToNotify[] = $oLock;
				}
			}
			if ( !empty( $aToNotify ) ) {
				$aEmailContent = [
					__( 'Shield has detected that the contents of important files on your WordPress site have changed.', 'wp-simple-firewall' ),
					sprintf( '%s: %s', __( 'URL', 'wp-simple-firewall' ), Services::WpGeneral()->getHomeUrl() ),
					'',
					__( 'The following files have either been changed or deleted.', 'wp-simple-firewall' ),
				];
				foreach ( $aToNotify as $oLock ) {
					$aEmailContent[] = '- '.$oLock->file;
					/** @var FileLocker\Update $oUpd */
					$oUpd = $oDbH->getQueryUpdater();
					$oUpd->markNotified( $oLock );
				}
				$aEmailContent[] = '';
				$aEmailContent[] = __( 'Use the link below to review the File Locker results.', 'wp-simple-firewall' );
				$aEmailContent[] = $this->getCon()->getModule_Insights()->getUrl_SubInsightsPage( 'scans' );
				$aEmailContent[] = '';
				$aEmailContent[] = __( 'Thank You.', 'wp-simple-firewall' );

				$sTitle = sprintf( '%s - %s', __( 'Important', 'wp-simple-firewall' ),
					sprintf( __( '%s File Locker Has Detected Critical File Changes', 'wp-simple-firewall' ),
						$this->getCon()->getHumanName() ) );
				$this->getMod()
					 ->getEmailProcessor()
					 ->sendEmailWithWrap(
						 $oMod->getPluginDefaultRecipientAddress(),
						 $sTitle,
						 $aEmailContent
					 );
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
		$oFile = new File( $sFileKey );
		$oFile->max_levels = $nLevels;
		$oFile->max_paths = $nMaxPaths;
		return $oFile;
	}
}
