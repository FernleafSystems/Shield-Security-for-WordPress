<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\FileLocker;
use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib;
use FernleafSystems\Wordpress\Services\Services;

class FileLockerController {

	use Modules\ModConsumer;
	use ExecOnce;

	protected function canRun() :bool {
		/** @var HackGuard\ModCon $mod */
		$mod = $this->getMod();
		return $this->isEnabled() && $mod->getDbHandler_FileLocker()->isReady();
	}

	public function isEnabled() :bool {
		/** @var HackGuard\Options $opts */
		$opts = $this->getOptions();
		return ( count( $opts->getFilesToLock() ) > 0 )
			   && $this->getCon()
					   ->getModule_Plugin()
					   ->getShieldNetApiController()
					   ->canHandshake();
	}

	protected function run() {
		$con = $this->getCon();
		add_filter( $con->prefix( 'admin_bar_menu_items' ), [ $this, 'addAdminMenuBarItem' ], 100 );

		add_action( $con->prefix( 'plugin_shutdown' ), function () {
			if ( !$this->getCon()->plugin_deactivating && !$this->getCon()->is_my_upgrade ) {
				if ( $this->getOptions()->isOptChanged( 'file_locker' ) ) {
					$this->deleteAllLocks();
				}
				else {
					$this->runAnalysis();
				}
			}
		} );
	}

	/**
	 * @param array $aItems
	 * @return array
	 */
	public function addAdminMenuBarItem( array $aItems ) {
		$nCountFL = $this->countProblems();
		if ( $nCountFL > 0 ) {
			$aItems[] = [
				'id'       => $this->getCon()->prefix( 'filelocker_problems' ),
				'title'    => __( 'File Locker', 'wp-simple-firewall' )
							  .sprintf( '<div class="wp-core-ui wp-ui-notification shield-counter"><span aria-hidden="true">%s</span></div>', $nCountFL ),
				'href'     => $this->getCon()->getModule_Insights()->getUrl_SubInsightsPage( 'scans' ),
				'warnings' => $nCountFL
			];
		}
		return $aItems;
	}

	/**
	 * @return int
	 */
	public function countProblems() {
		return count( ( new Ops\LoadFileLocks() )
			->setMod( $this->getMod() )
			->withProblems() );
	}

	/**
	 * @param FileLocker\EntryVO $VO
	 * @return string[]
	 */
	public function createFileDownloadLinks( $VO ) {
		/** @var HackGuard\ModCon $mod */
		$mod = $this->getMod();
		$aLinks = [];
		foreach ( [ 'original', 'current' ] as $sType ) {
			$aActionNonce = $mod->getNonceActionData( 'filelocker_download_'.$sType );
			$aActionNonce[ 'rid' ] = $VO->id;
			$aActionNonce[ 'rand' ] = rand();
			$aLinks[ $sType ] = add_query_arg( $aActionNonce, $mod->getUrl_AdminPage() );
		}
		return $aLinks;
	}

	public function handleFileDownloadRequest() {
		$oReq = Services::Request();
		$oLock = $this->getFileLock( (int)$oReq->query( 'rid', 0 ) );

		if ( $oLock instanceof FileLocker\EntryVO ) {
			$sType = str_replace( 'filelocker_download_', '', $oReq->query( 'exec' ) );

			// Note: Download what's on the disk if nothing is changed.
			if ( $sType == 'current' ) {
				$sContent = Services::WpFs()->getFileContent( $oLock->file );
			}
			elseif ( $sType == 'original' ) {
				$sContent = ( new Lib\FileLocker\Ops\ReadOriginalFileContent() )
					->setMod( $this->getMod() )
					->run( $oLock );
			}

			if ( !empty( $sContent ) ) {
				header( 'Set-Cookie: fileDownload=true; path=/' );
				Services::Response()
						->downloadStringAsFile( $sContent, strtoupper( $sType ).'-'.basename( $oLock->file ) );
			}
		}

		wp_die( "Something about this request wasn't right" );
	}

	public function deleteAllLocks() {
		/** @var HackGuard\ModCon $mod */
		$mod = $this->getMod();
		$mod->getDbHandler_FileLocker()->tableDelete( true );
	}

	public function purge() {
		/** @var HackGuard\ModCon $mod */
		$mod = $this->getMod();
		$mod->getDbHandler_FileLocker()->tableDelete();
	}

	/**
	 * @param int $ID
	 * @return FileLocker\EntryVO|null
	 */
	public function getFileLock( $ID ) {
		return ( new Lib\FileLocker\Ops\LoadFileLocks() )
				   ->setMod( $this->getMod() )
				   ->loadLocks()[ $ID ] ?? null;
	}

	private function runAnalysis() {
		if ( did_action( 'upgrader_process_complete' ) ) {
			return; // @deprecated 10.3 - temporary to prevent upgrade notices/errors
		}

		/** @var Modules\HackGuard\Options $opts */
		$opts = $this->getOptions();

		// 1. First assess the existing locks for changes.
		( new Ops\AssessLocks() )
			->setMod( $this->getMod() )
			->run();

		// 2. Create new file locks as required
		foreach ( $opts->getFilesToLock() as $fileKey ) {
			try {
				( new Ops\CreateFileLocks() )
					->setMod( $this->getMod() )
					->setWorkingFile( $this->getFile( $fileKey ) )
					->create();
			}
			catch ( \Exception $e ) {
				error_log( $e->getMessage() );
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
				$nMaxPaths = 1;
				$nLevels = $bIsSplitWp ? 3 : 2;

				$openBaseDir = ini_get( 'open_basedir' );
				if ( !empty( $openBaseDir ) ) {
					$nLevels--;
				}
				// TODO: is split URL?
				break;

			case 'root_htaccess':
				$sFileKey = '.htaccess';
				$nLevels = $bIsSplitWp ? 2 : 1;
				break;

			case 'root_webconfig':
				$sFileKey = 'Web.Config';
				$nLevels = $bIsSplitWp ? 2 : 1;
				break;

			case 'root_index':
				$sFileKey = 'index.php';
				$nLevels = $bIsSplitWp ? 2 : 1;
				break;
			default:
				if ( Services::WpFs()->isAbsPath( $sFileKey ) && Services::WpFs()->isFile( $sFileKey ) ) {
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
