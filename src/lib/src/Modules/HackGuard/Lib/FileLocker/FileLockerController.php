<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\FileLocker\Ops as FileLockerDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Exceptions\{
	FileContentsEncodingFailure,
	FileContentsEncryptionFailure,
	LockDbInsertFailure,
	NoCipherAvailableException,
	NoFileLockPathsExistException,
	PublicKeyRetrievalFailure,
	UnsupportedFileLockType
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Encrypt\CipherTests;

class FileLockerController {

	use ExecOnce;
	use ModConsumer;

	public const CRON_DELAY = 60;

	private $locks = null;

	protected function canRun() :bool {
		return $this->isEnabled();
	}

	public function isEnabled() :bool {
		return ( \count( $this->opts()->getFilesToLock() ) > 0 )
			   && $this->mod()->getDbH_FileLocker()->isReady()
			   && self::con()
					  ->getModule_Plugin()
					  ->getShieldNetApiController()
					  ->canHandshake();
	}

	protected function run() {
		add_action( self::con()->prefix( 'pre_plugin_shutdown' ), function () {
			$this->runAnalysis();
		} );
		add_filter( self::con()->prefix( 'admin_bar_menu_items' ), [ $this, 'addAdminMenuBarItem' ], 100 );
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
				'rand' => \uniqid(),
			] );
		}
		return $links;
	}

	/**
	 * @return FileLockerDB\Record[]
	 */
	public function getLocks() :array {
		if ( \is_null( $this->locks ) ) {
			$this->locks = ( new Ops\LoadFileLocks() )->loadLocks();
		}
		return $this->locks;
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
		$this->mod()->getDbH_FileLocker()->tableDelete();
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

	/**
	 * @deprecated 18.5 - switch to private.
	 */
	public function runAnalysis() {
		if ( $this->getState()[ 'abspath' ] !== ABSPATH || !Services::Encrypt()->isSupportedOpenSslDataEncryption() ) {
			$this->opts()->setOpt( 'file_locker', [] );
			$this->setState( [] );
			$this->purge();
		}
		else {
			// 1. Look for any changes in config: has a lock type been removed?
			$locks = \method_exists( $this, 'getLocks' ) ? $this->getLocks() : ( new Ops\LoadFileLocks() )->loadLocks();
			foreach ( $locks as $lock ) {
				if ( !\in_array( $lock->type, $this->opts()->getFilesToLock() ) ) {
					( new Ops\DeleteFileLock() )->delete( $lock );
				}
			}

			// 2. Assess existing locks for file modifications.
			( new Ops\AssessLocks() )->run();

			// 3. Create any outstanding locks.
			if ( is_main_network() ) {
				$this->maybeRunLocksCreation();
			}
		}
	}

	private function maybeRunLocksCreation() {
		if ( !empty( ( new Ops\GetFileLocksToCreate() )->run() ) ) {
			$con = self::con();
			$hook = $con->prefix( 'create_file_locks' );

			if ( wp_next_scheduled( $hook ) ) {
				add_action( $hook, function () {
					$this->runLocksCreation();
				} );
			}
			elseif ( !Services::WpGeneral()->isCron() ) {
				wp_schedule_single_event( Services::Request()->ts() + self::CRON_DELAY, $hook );
			}
		}
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
					$this->opts()->setOpt( 'file_locker', \array_diff( $this->opts()->getFilesToLock(), [ $type ] ) );
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
		return \array_merge(
			[
				'abspath'                      => ABSPATH,
				'last_locks_created_at'        => 0,
				'last_locks_created_failed_at' => 0,
				'last_error'                   => '',
				'cipher'                       => '',
				'cipher_last_checked_at'       => 0,
			],
			$this->opts()->getOpt( 'filelocker_state' )
		);
	}

	protected function setState( array $state ) {
		$this->opts()->setOpt( 'filelocker_state', $state );
		$this->mod()->saveModOptions();
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
					$state[ 'cipher' ] = \array_pop( $ciphers );
					$this->setState( $state );
				}
			}
			catch ( \Exception $e ) {
			}
		}

		return !empty( $state[ 'cipher' ] );
	}
}