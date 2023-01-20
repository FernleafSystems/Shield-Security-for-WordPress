<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\FileLocker\Ops as FileLockerDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Services\Utilities\Encrypt\CipherTests;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Exceptions\{
	FileContentsEncodingFailure,
	FileContentsEncryptionFailure,
	LockDbInsertFailure,
	NoCipherAvailableException,
	NoFileLockPathsExistException,
	PublicKeyRetrievalFailure,
	UnsupportedFileLockType
};
use FernleafSystems\Wordpress\Services\Services;

class FileLockerController extends Modules\Base\Common\ExecOnceModConsumer {

	public const CRON_DELAY = 60;

	protected function canRun() :bool {
		return $this->isEnabled();
	}

	public function isEnabled() :bool {
		$con = $this->getCon();
		/** @var HackGuard\ModCon $mod */
		$mod = $this->getMod();
		/** @var HackGuard\Options $opts */
		$opts = $this->getOptions();
		return $con->isPremiumActive()
			   && ( count( $opts->getFilesToLock() ) > 0 )
			   && $mod->getDbH_FileLocker()->isReady()
			   && $con->getModule_Plugin()
					  ->getShieldNetApiController()
					  ->canHandshake();
	}

	protected function run() {
		$con = $this->getCon();
		add_action( 'wp_loaded', [ $this, 'runAnalysis' ] );
		add_filter( $con->prefix( 'admin_bar_menu_items' ), [ $this, 'addAdminMenuBarItem' ], 100 );
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

	public function createFileDownloadLinks( FileLockerDB\Record $lock ) :array {
		$links = [];
		foreach ( [ 'original', 'current' ] as $type ) {
			$links[ $type ] = $this->getCon()->plugin_urls->fileDownload( 'filelocker', [
				'type' => $type,
				'rid'  => $lock->id,
				'rand' => uniqid(),
			] );
		}
		return $links;
	}

	/**
	 * @throws \Exception
	 */
	public function handleFileDownloadRequest() :array {
		$req = Services::Request();

		$lock = $this->getFileLock( (int)$req->query( 'rid', 0 ) );
		$type = $req->query( 'type' );

		// Note: Download what's on the disk if nothing is changed.
		if ( $type == 'current' ) {
			$content = Services::WpFs()->getFileContent( $lock->path );
		}
		elseif ( $type == 'original' ) {
			$content = ( new Ops\ReadOriginalFileContent() )
				->setMod( $this->getMod() )
				->run( $lock );
		}
		else {
			throw new \Exception( 'Invalid file locker type download' );
		}

		if ( empty( $content ) ) {
			throw new \Exception( 'File contents are empty.' );
		}

		return [
			'name'    => strtoupper( $type ).'-'.basename( $lock->path ),
			'content' => $content,
		];
	}

	public function purge() {
		/** @var HackGuard\ModCon $mod */
		$mod = $this->getMod();
		$mod->getDbH_FileLocker()->tableDelete();
	}

	/**
	 * @throws \Exception
	 */
	public function getFileLock( int $ID ) :FileLockerDB\Record {
		$lock = ( new Ops\LoadFileLocks() )
					->setMod( $this->getMod() )
					->loadLocks()[ $ID ] ?? null;
		if ( empty( $lock ) ) {
			throw new \Exception( 'Not a valid Lock File record' );
		}
		return $lock;
	}

	public function runAnalysis() {
		/** @var HackGuard\Options $opts */
		$opts = $this->getOptions();

		if ( $this->getState()[ 'abspath' ] !== ABSPATH || !Services::Encrypt()->isSupportedOpenSslDataEncryption() ) {
			$opts->setOpt( 'file_locker', [] );
			$this->setState( [] );
			$this->purge();
		}
		else {
			// 1. Look for any changes in config: has a lock type been removed?
			foreach ( ( new Ops\LoadFileLocks() )->setMod( $this->getMod() )->loadLocks() as $lock ) {
				if ( !in_array( $lock->type, $opts->getFilesToLock() ) ) {
					( new Ops\DeleteFileLock() )
						->setMod( $this->getMod() )
						->delete( $lock );
				}
			}

			// 2. Assess existing locks for file modifications.
			( new Ops\AssessLocks() )
				->setMod( $this->getMod() )
				->run();

			// 3. Create any outstanding locks.
			if ( is_main_network() ) {
				$this->maybeRunLocksCreation();
			}
		}
	}

	private function maybeRunLocksCreation() {
		if ( !empty( ( new Ops\GetFileLocksToCreate() )->setMod( $this->getMod() )->run() ) ) {
			$con = $this->getCon();

			if ( !Services::WpGeneral()->isCron() ) {
				if ( !wp_next_scheduled( $con->prefix( 'create_file_locks' ) ) ) {
					wp_schedule_single_event(
						Services::Request()->ts() + self::CRON_DELAY,
						$con->prefix( 'create_file_locks' )
					);
				}
			}

			add_action( $con->prefix( 'create_file_locks' ), function () {
				$this->runLocksCreation();
			} );
		}
	}

	/**
	 * There's at least 60 seconds between each attempt to create a file lock.
	 * This ensures our API isn't bombarded by sites that, for some reason, fail to store the lock in the DB.
	 */
	private function runLocksCreation() {
		/** @var HackGuard\Options $opts */
		$opts = $this->getOptions();

		$now = Services::Request()->ts();
		$filesToLock = ( new Ops\GetFileLocksToCreate() )->setMod( $this->getMod() )->run();

		$state = $this->getState();
		if ( !empty( $filesToLock )
			 && $now - $state[ 'last_locks_created_at' ] > 1
			 && $now - $state[ 'last_locks_created_failed_at' ] > 1
		) {
			foreach ( $filesToLock as $fileType ) {
				try {
					if ( !$this->canEncrypt() ) {
						throw new NoCipherAvailableException();
					}

					( new Ops\CreateFileLocks() )
						->setMod( $this->getMod() )
						->setWorkingFile( ( new Ops\BuildFileFromFileKey() )->build( $fileType ) )
						->create();
					$state[ 'last_locks_created_at' ] = $now;
					$state[ 'last_error' ] = '';
				}
				catch ( NoFileLockPathsExistException | LockDbInsertFailure
				| FileContentsEncodingFailure | FileContentsEncryptionFailure
				| NoCipherAvailableException | PublicKeyRetrievalFailure
				| UnsupportedFileLockType $e ) {
					// Remove the key if there are no files on-disk to lock
					$opts->setOpt( 'file_locker', array_diff( $opts->getFilesToLock(), [ $fileType ] ) );
					error_log( $e->getMessage() );
				}
				catch ( \Exception $e ) {
					$state[ 'last_error' ] = $e->getMessage();
					$state[ 'last_locks_created_failed_at' ] = $now;
					error_log( $e->getMessage() );
					break;
				}
			}

			$state[ 'abspath' ] = ABSPATH;
			$this->setState( $state );
		}
	}

	public function getState() :array {
		$state = $this->getOptions()->getOpt( 'filelocker_state' );
		return array_merge(
			[
				'abspath'                      => ABSPATH,
				'last_locks_created_at'        => 0,
				'last_locks_created_failed_at' => 0,
				'last_error'                   => '',
				'cipher'                       => '',
				'cipher_last_checked_at'       => 0,
			],
			is_array( $state ) ? $state : []
		);
	}

	protected function setState( array $state ) {
		$this->getOptions()->setOpt( 'filelocker_state', $state );
		$this->getMod()->saveModOptions();
	}

	/**
	 * Ensure this is run on a cron, so that we're not running cipher tests on every page load.
	 */
	public function canEncrypt() :bool {
		$state = $this->getState();

		if ( Services::Request()->carbon()->subSecond()->timestamp > $state[ 'cipher_last_checked_at' ] ) {
			$state[ 'cipher_last_checked_at' ] = Services::Request()->ts();
			$this->setState( $state );

			try {
				$ciphers = ( new CipherTests() )->findAvailableCiphers();
				if ( !empty( $ciphers ) ) {
					$state[ 'cipher' ] = array_pop( $ciphers );
					$this->setState( $state );
				}
			}
			catch ( \Exception $e ) {
			}
		}

		return !empty( $state[ 'cipher' ] );
	}

	/**
	 * @deprecated 17.0
	 */
	public function checkLockConfig() {
	}

	/**
	 * @deprecated 17.0
	 */
	private function isFileLockerStateChanged() :bool {
		return false;
	}

	/**
	 * @deprecated 17.0
	 */
	public function canSslEncryption() :bool {
		return Services::Encrypt()->isSupportedOpenSslDataEncryption();
	}

	/**
	 * @deprecated 17.0
	 */
	public function deleteAllLocks() {
	}
}