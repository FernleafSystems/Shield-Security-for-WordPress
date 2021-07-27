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
	 * @throws \ErrorException
	 */
	public function run( FileLocker\EntryVO $lock ) :bool {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$publicKey = $this->getPublicKey();
		$raw = ( new BuildEncryptedFilePayload() )
			->setMod( $mod )
			->build( $lock->file, reset( $publicKey ) );

		/** @var FileLocker\Update $updater */
		$updater = $mod->getDbHandler_FileLocker()->getQueryUpdater();
		$success = $updater->updateEntry( $lock, [
			'hash_original' => hash_file( 'sha1', $lock->file ),
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