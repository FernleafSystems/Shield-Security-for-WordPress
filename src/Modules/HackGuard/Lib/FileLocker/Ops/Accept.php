<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\FileLocker\Ops as FileLockerDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Exceptions\NoCipherAvailableException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Utility\RetrievePublicKey;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class Accept {

	use PluginControllerConsumer;

	/**
	 * Keeps the file's current contents as its new locked copy.
	 * @throws \Exception
	 */
	public function run( FileLockerDB\Record $lock ) :bool {
		// The owner confirmed keeping changed contents: if the file has since gone, that's not what they agreed to.
		if ( !Services::WpFs()->isAccessibleFile( $lock->path ) ) {
			throw new \Exception( 'The file is missing, so its changes cannot be accepted.' );
		}

		$FL = self::con()->comps->file_locker;
		$state = $FL->getState();

		// Depending on timing, the preferred cipher may not have been set, so we force a check.
		if ( empty( $state[ 'cipher' ] ) ) {
			$FL->canEncrypt( true );
			$state = $FL->getState();
		}

		if ( empty( $state[ 'cipher' ] ) ) {
			throw new NoCipherAvailableException();
		}

		$publicKey = ( new RetrievePublicKey() )->retrieve();

		$raw = ( new BuildEncryptedFilePayload() )->fromPath( $lock->path, \reset( $publicKey ), $state[ 'cipher' ] );

		$success = self::con()
			->db_con
			->file_locker
			->getQueryUpdater()
			->updateRecord( $lock, [
				'hash_original' => \hash_file( 'sha1', $lock->path ),
				'content'       => \base64_encode( $raw ),
				'public_key_id' => \key( $publicKey ),
				'cipher'        => $state[ 'cipher' ],
				'detected_at'   => 0,
				'updated_at'    => Services::Request()->ts(),
				'created_at'    => Services::Request()->ts(), // update "locked at"
			] );

		if ( $success ) {
			$FL->reassessLocksNow();
		}
		return $success;
	}

	/**
	 * The file is gone and the owner accepts that: File Locker stops locking it, just as if it were unticked in the
	 * File Locker settings. Saving that runs the usual clean-up, which deletes the lock of a file no longer selected.
	 * @throws \Exception
	 */
	public function acceptRemoval( FileLockerDB\Record $lock ) :bool {
		// The owner confirmed the file is gone: if it's back, dropping its locked copy is not what they agreed to.
		if ( Services::WpFs()->isAccessibleFile( $lock->path ) ) {
			throw new \Exception( 'The file exists again, so its removal cannot be accepted.' );
		}

		$FL = self::con()->comps->file_locker;
		self::con()
			->opts
			->optSet( 'file_locker', \array_values( \array_diff( $FL->getFilesToLock(), [ $lock->type ] ) ) )
			->store();
		$FL->clearLocks();
		return true;
	}
}
