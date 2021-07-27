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
		add_action( 'wp_loaded', [ $this, 'runAnalysis' ] );
		add_action( $con->prefix( 'pre_plugin_shutdown' ), [ $this, 'checkLockConfig' ] );
		add_filter( $con->prefix( 'admin_bar_menu_items' ), [ $this, 'addAdminMenuBarItem' ], 100 );
	}

	public function checkLockConfig() {
		if ( !$this->getCon()->plugin_deactivating && $this->isFileLockerStateChanged() ) {
			$this->deleteAllLocks();
			$this->setState( [] );
		}
	}

	private function isFileLockerStateChanged() :bool {
		return $this->getOptions()->isOptChanged( 'file_locker' ) || $this->getState()[ 'abspath' ] !== ABSPATH;
	}

	public function addAdminMenuBarItem( array $items ) :array {
		$problems = $this->countProblems();
		if ( $problems > 0 ) {
			$items[] = [
				'id'       => $this->getCon()->prefix( 'filelocker_problems' ),
				'title'    => __( 'File Locker', 'wp-simple-firewall' )
							  .sprintf( '<div class="wp-core-ui wp-ui-notification shield-counter"><span aria-hidden="true">%s</span></div>', $problems ),
				'href'     => $this->getCon()->getModule_Insights()->getUrl_ScansResults(),
				'warnings' => $problems
			];
		}
		return $items;
	}

	public function countProblems() :int {
		return count( ( new Ops\LoadFileLocks() )
			->setMod( $this->getMod() )
			->withProblems() );
	}

	public function createFileDownloadLinks( FileLocker\EntryVO $lock ) :array {
		/** @var HackGuard\ModCon $mod */
		$mod = $this->getMod();
		$links = [];
		foreach ( [ 'original', 'current' ] as $type ) {
			$links[ $type ] = $mod->createFileDownloadLink( 'filelocker', [
				'type' => $type,
				'rid'  => $lock->id,
				'rand' => uniqid(),
			] );
		}
		return $links;
	}

	public function handleFileDownloadRequest() {
		$req = Services::Request();
		$lock = $this->getFileLock( (int)$req->query( 'rid', 0 ) );

		if ( $lock instanceof FileLocker\EntryVO ) {
			$type = $req->query( 'type' );

			// Note: Download what's on the disk if nothing is changed.
			if ( $type == 'current' ) {
				$content = Services::WpFs()->getFileContent( $lock->file );
			}
			elseif ( $type == 'original' ) {
				$content = ( new Lib\FileLocker\Ops\ReadOriginalFileContent() )
					->setMod( $this->getMod() )
					->run( $lock );
			}
			else {
				throw new \Exception( 'Invalid file locker type download' );
			}

			if ( !empty( $content ) ) {
				header( 'Set-Cookie: fileDownload=true; path=/' );
				Services::Response()
						->downloadStringAsFile( $content, strtoupper( $type ).'-'.basename( $lock->file ) );
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

	public function runAnalysis() {
		// 1. First assess the existing locks for changes.
		( new Ops\AssessLocks() )
			->setMod( $this->getMod() )
			->run();

		// 2. Create any outstanding locks.
		if ( is_main_network() ) {
			$this->runLocksCreation();
		}
	}

	/**
	 * There's at least 15 seconds between each attempt to create a file lock.
	 * This ensures our API isn't bombarded by sites that, for some reason, fail to store the lock in the DB.
	 */
	private function runLocksCreation() {
		/** @var Modules\HackGuard\Options $opts */
		$opts = $this->getOptions();
		$filesToLock = $opts->getFilesToLock();

		$state = $this->getState();
		if ( !empty( $filesToLock ) && Services::Request()->ts() - $state[ 'last_locks_created_at' ] > 60 ) {

			$lockCreated = false;
			foreach ( $opts->getFilesToLock() as $fileKey ) {
				try {
					$lockCreated = ( new Ops\CreateFileLocks() )
						->setMod( $this->getMod() )
						->setWorkingFile( $this->getFile( $fileKey ) )
						->create();
				}
				catch ( \Exception $e ) {
					error_log( $e->getMessage() );
				}
			}
			if ( $lockCreated ) {
				$state[ 'last_locks_created_at' ] = Services::Request()->ts();
			}

			$state[ 'abspath' ] = ABSPATH;
			$this->setState( $state );
		}
	}

	/**
	 * @param string $fileKey
	 * @return File
	 * @throws \Exception
	 */
	private function getFile( string $fileKey ) :File {
		$isSplitWpUrl = false; // TODO: is split URL?
		$maxPaths = 1;
		switch ( $fileKey ) {
			case 'wpconfig':
				$fileKey = 'wp-config.php';
				$maxPaths = 1;
				$levels = $isSplitWpUrl ? 3 : 2;
				$openBaseDir = ini_get( 'open_basedir' );
				if ( !empty( $openBaseDir ) ) {
					$levels--;
				}
				break;

			case 'root_htaccess':
				$fileKey = '.htaccess';
				$levels = $isSplitWpUrl ? 2 : 1;
				break;

			case 'root_webconfig':
				$fileKey = 'Web.Config';
				$levels = $isSplitWpUrl ? 2 : 1;
				break;

			case 'root_index':
				$fileKey = 'index.php';
				$levels = $isSplitWpUrl ? 2 : 1;
				break;
			default:
				if ( Services::WpFs()->isAbsPath( $fileKey ) && Services::WpFs()->isFile( $fileKey ) ) {
					$levels = 1;
					$maxPaths = 1;
				}
				else {
					throw new \Exception( 'Not a supported file lock type' );
				}
				break;
		}

		$file = new File( $fileKey );
		$file->max_levels = $levels;
		$file->max_paths = $maxPaths;
		return $file;
	}

	protected function getState() :array {
		/** @var HackGuard\Options $opts */
		$opts = $this->getOptions();
		return array_merge(
			[
				'abspath'               => ABSPATH,
				'last_locks_created_at' => 0,
			],
			is_array( $opts->getOpt( 'filelocker_state' ) ) ? $opts->getOpt( 'filelocker_state' ) : []
		);
	}

	protected function setState( array $state ) {
		$this->getOptions()->setOpt( 'filelocker_state', $state );
	}
}