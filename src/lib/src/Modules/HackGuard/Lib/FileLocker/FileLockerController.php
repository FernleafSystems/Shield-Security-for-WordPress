<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Crons\PluginCronsConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\FileLocker\Ops as FileLockerDB;
use FernleafSystems\Wordpress\Services\Utilities\PasswordGenerator;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Exceptions\{
	FileContentsEncodingFailure,
	FileContentsEncryptionFailure,
	LockDbInsertFailure,
	NoCipherAvailableException,
	NoFileLockPathsExistException,
	PublicKeyRetrievalFailure,
	UnsupportedFileLockType
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class FileLockerController {

	use ExecOnce;
	use PluginControllerConsumer;
	use PluginCronsConsumer;

	public const CRON_DELAY = 60;

	private ?array $locks = null;

	public function isEnabled() :bool {
		return ( \count( $this->getFilesToLock() ) > 0 )
			   && self::con()->db_con->file_locker->isReady()
			   && self::con()->comps->shieldnet->canHandshake();
	}

	protected function canRun() :bool {
		return $this->isEnabled();
	}

	protected function run() {
		add_action( 'wp_loaded', function () {

			if ( !self::con()->this_req->wp_is_cron ) {
				$this->runAnalysis();
			}

			if ( wp_next_scheduled( $this->getCronHook() ) ) {
				add_action( $this->getCronHook(), fn() => $this->runLocksCreation() );
			}
		}, 1000 );

		add_filter( self::con()->prefix( 'admin_bar_menu_items' ), [ $this, 'addAdminMenuBarItem' ], 100 );

		$this->setupCronHooks();
	}

	public function addAdminMenuBarItem( array $items ) :array {
		$count = \count( ( new Ops\LoadFileLocks() )->withProblems() );
		if ( $count > 0 ) {
			$items[] = [
				'id'       => self::con()->prefix( 'filelocker_problems' ),
				'title'    => __( 'File Locker', 'wp-simple-firewall' )
							  .sprintf( '<div class="wp-core-ui wp-ui-notification shield-counter"><span aria-hidden="true">%s</span></div>', $count ),
				'href'     => self::con()->plugin_urls->adminTopNav( PluginNavs::NAV_SCANS, PluginNavs::SUBNAV_SCANS_RESULTS ),
				'warnings' => $count
			];
		}
		return $items;
	}

	public function createFileDownloadLinks( FileLockerDB\Record $lock ) :array {
		$links = [];
		foreach ( [ 'original', 'current' ] as $type ) {
			$links[ $type ] = self::con()->plugin_urls->fileDownload( 'filelocker', [
				'type' => $type,
				'rid'  => $lock->id,
				'rand' => PasswordGenerator::Gen( 6, false, true, false ),
			] );
		}
		return $links;
	}

	public function getFilesToLock() :array {
		return self::con()->opts->optGet( 'file_locker' );
	}

	/**
	 * @return FileLockerDB\Record[]
	 */
	public function getLocks() :array {
		return $this->locks ??= ( new Ops\LoadFileLocks() )->loadLocks();
	}

	public function clearLocks() :void {
		$this->locks = null;
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
			$content = ( new Ops\ReadOriginalFileContent() )->run( $lock );
		}
		else {
			throw new \Exception( 'Invalid file locker type download' );
		}

		if ( empty( $content ) ) {
			throw new \Exception( 'File contents are empty.' );
		}

		return [
			'name'    => \strtoupper( $type ).'-'.\basename( $lock->path ),
			'content' => $content,
		];
	}

	public function purge() {
		self::con()->db_con->file_locker->tableDelete();
	}

	/**
	 * @throws \Exception
	 */
	public function getFileLock( int $ID ) :FileLockerDB\Record {
		$lock = $this->getLocks()[ $ID ] ?? null;
		if ( empty( $lock ) ) {
			throw new \Exception( 'Not a valid Lock File record' );
		}
		return $lock;
	}

	private function runAnalysis() {
		if ( \version_compare( self::con()->cfg->version(), '19.0.7', '<=' ) ) {
			return;
		}

		if ( $this->getState()[ 'abspath' ] !== ABSPATH || !Services::Encrypt()->isSupportedOpenSslDataEncryption() ) {
			self::con()->opts->optSet( 'file_locker', [] );
			$this->setState( [] );
			$this->purge();
		}
		else {
			// 1. Look for any changes in config: has a lock type been removed?
			( new Ops\CleanLockRecords() )->run();

			// 2. Assess existing locks for file modifications.
			( new Ops\AssessLocks() )->run();

			// 3. Create any outstanding locks.
			if ( is_main_network()
				 && !wp_next_scheduled( $this->getCronHook() )
				 && !Services::WpGeneral()->isCron()
				 && !empty( ( new Ops\GetFileLocksToCreate() )->run() )
			) {
				wp_schedule_single_event( Services::Request()->ts() + self::CRON_DELAY, $this->getCronHook() );
			}
		}
	}

	private function getCronHook() :string {
		return self::con()->prefix( 'create_file_locks' );
	}

	/**
	 * There's at least 60 seconds between each attempt to create a file lock.
	 * This ensures our API isn't bombarded by sites that, for some reason, fail to store the lock in the DB.
	 */
	private function runLocksCreation() {
		$now = Services::Request()->ts();
		$filesToLock = ( new Ops\GetFileLocksToCreate() )->run();

		$state = $this->getState();
		if ( !empty( $filesToLock )
			 && $now - $state[ 'last_locks_created_at' ] > 1
			 && $now - $state[ 'last_locks_created_failed_at' ] > 1
		) {
			foreach ( $filesToLock as $type ) {
				try {
					if ( !$this->canEncrypt() ) {
						throw new NoCipherAvailableException();
					}

					( new Ops\CreateFileLocks() )
						->setWorkingFile( ( new Ops\BuildFileFromFileKey() )->build( $type ) )
						->create();
					$state[ 'last_locks_created_at' ] = $now;
					$state[ 'last_error' ] = '';
				}
				catch ( NoFileLockPathsExistException|LockDbInsertFailure
				|FileContentsEncodingFailure|FileContentsEncryptionFailure
				|NoCipherAvailableException|PublicKeyRetrievalFailure
				|UnsupportedFileLockType $e ) {
					// Remove the key if there are no files on-disk to lock
					self::con()->opts->optSet( 'file_locker', \array_diff( $this->getFilesToLock(), [ $type ] ) );
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
		return \array_merge( [
			'abspath'                      => ABSPATH,
			'last_locks_created_at'        => 0,
			'last_locks_created_failed_at' => 0,
			'last_error'                   => '',
			'cipher'                       => '',
			'cipher_last_checked_at'       => 0,
		], self::con()->opts->optGet( 'filelocker_state' ) );
	}

	protected function setState( array $state ) {
		self::con()->opts->optSet( 'filelocker_state', $state )->store();
	}

	/**
	 * Ensure this is run on a cron, so that we're not running cipher tests on every page load.
	 */
	public function canEncrypt( bool $forceCheck = false ) :bool {
		$state = $this->getState();

		if ( $forceCheck || Services::Request()->carbon()->subDay()->timestamp > $state[ 'cipher_last_checked_at' ] ) {

			$state[ 'cipher_last_checked_at' ] = Services::Request()->ts();
			$this->setState( $state );

			$state[ 'cipher' ] = ( new Ops\GetAvailableCiphers() )->firstFull();
			$this->setState( $state );
		}

		return !empty( $state[ 'cipher' ] );
	}

	public function runDailyCron() {
		( new Ops\UpgradeLocks() )->run();
	}
}