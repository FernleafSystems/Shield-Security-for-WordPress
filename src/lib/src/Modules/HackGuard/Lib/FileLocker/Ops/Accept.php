<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\FileLocker\Ops as FileLockerDB;
use FernleafSystems\Wordpress\Services\Services;

class Accept extends BaseOps {

	/**
	 * @throws \Exception
	 */
	public function run( FileLockerDB\Record $lock ) :bool {
		$FL = $this->mod()->getFileLocker();
		$state = $FL->getState();

		// Depending on timing, the preferred cipher may not have been set, so we force a check.
		if ( empty( $state[ 'cipher' ] ) ) {
			$FL->canEncrypt();
			$state = $FL->getState();
		}

		$cipher = empty( $state[ 'cipher' ] ) ? 'rc4' : $state[ 'cipher' ];

		$publicKey = $this->getPublicKey();

		$raw = ( new BuildEncryptedFilePayload() )->fromPath( $lock->path, \reset( $publicKey ), $cipher );

		/** @var FileLockerDB\Update $updater */
		$updater = $this->mod()->getDbH_FileLocker()->getQueryUpdater();
		$success = $updater->updateRecord( $lock, [
			'hash_original' => \hash_file( 'sha1', $lock->path ),
			'content'       => \base64_encode( $raw ),
			'public_key_id' => \key( $publicKey ),
			'cipher'        => $cipher,
			'detected_at'   => 0,
			'updated_at'    => Services::Request()->ts(),
			'created_at'    => Services::Request()->ts(), // update "locked at"
		] );

		$FL->clearLocks();
		return $success;
	}
}