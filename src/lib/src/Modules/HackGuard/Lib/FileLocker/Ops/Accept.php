<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\FileLocker\Ops as FileLockerDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Exceptions\NoCipherAvailableException;
use FernleafSystems\Wordpress\Services\Services;

class Accept extends BaseOps {

	/**
	 * @throws \Exception
	 */
	public function run( FileLockerDB\Record $lock ) :bool {
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

		$publicKey = $this->getPublicKey();

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

		$FL->clearLocks();
		return $success;
	}
}