<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\FileLocker;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class Accept
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops
 */
class Accept extends BaseOps {

	/**
	 * @param FileLocker\EntryVO $lock
	 * @return bool
	 * @throws \ErrorException
	 */
	public function run( $lock ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$aPublicKey = $this->getPublicKey();
		$raw = ( new BuildEncryptedFilePayload() )
			->setMod( $mod )
			->build( $lock->file, reset( $aPublicKey ) );

		/** @var FileLocker\Update $updater */
		$updater = $mod->getDbHandler_FileLocker()->getQueryUpdater();
		$success = $updater->updateEntry( $lock, [
			'hash_original' => hash_file( 'sha1', $lock->file ),
			'content'       => base64_encode( $raw ),
			'public_key_id' => key( $aPublicKey ),
			'detected_at'   => 0,
			'updated_at'    => Services::Request()->ts(),
			'created_at'    => Services::Request()->ts(), // update "locked at"
		] );

		$this->clearFileLocksCache();
		return $success;
	}
}