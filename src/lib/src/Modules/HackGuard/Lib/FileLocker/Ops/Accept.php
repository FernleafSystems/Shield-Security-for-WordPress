<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\FileLocker\Ops as FileLockerDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Services\Services;

class Accept extends BaseOps {

	/**
	 * @throws \Exception
	 */
	public function run( FileLockerDB\Record $lock ) :bool {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$publicKey = $this->getPublicKey();
		$raw = ( new BuildEncryptedFilePayload() )
			->setMod( $mod )
			->build( (string)$lock->path, reset( $publicKey ) );

		/** @var FileLockerDB\Update $updater */
		$updater = $mod->getDbH_FileLocker()->getQueryUpdater();
		$success = $updater->updateEntry( $lock, [
			'hash_original' => hash_file( 'sha1', $lock->path ),
			'content'       => base64_encode( $raw ),
			'public_key_id' => key( $publicKey ),
			'detected_at'   => 0,
			'updated_at'    => Services::Request()->ts(),
			'created_at'    => Services::Request()->ts(), // update "locked at"
		] );

		$this->clearFileLocksCache();
		return $success;
	}
}