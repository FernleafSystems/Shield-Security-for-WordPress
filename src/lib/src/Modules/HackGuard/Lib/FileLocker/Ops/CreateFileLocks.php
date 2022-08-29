<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\FileLocker;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Services\Services;

class CreateFileLocks extends BaseOps {

	/**
	 * @throws \Exception
	 */
	public function create() {
		foreach ( $this->file->getExistingPossiblePaths() as $path ) {
			if ( empty( $this->findLockRecordForFile() ) ) {
				$this->processPath( $path );
			}
		}
	}

	/**
	 * @throws \Exception
	 */
	private function processPath( string $path ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		if ( Services::WpFs()->isFile( $path ) ) {
			$entry = new FileLocker\EntryVO();
			$entry->file = $path;
			$entry->hash_original = hash_file( 'sha1', $path );

			$publicKey = $this->getPublicKey();
			$entry->public_key_id = key( $publicKey );
			$entry->content = ( new BuildEncryptedFilePayload() )
				->setMod( $mod )
				->build( $path, reset( $publicKey ) );

			/** @var FileLocker\Insert $inserter */
			$inserter = $mod->getDbHandler_FileLocker()->getQueryInserter();
			if ( !$inserter->insert( $entry ) ) {
				throw new \Exception( 'Failed to insert file locker record.' );
			}

			$this->clearFileLocksCache();
		}
	}
}