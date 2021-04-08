<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\FileLocker;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class CreateFileLocks
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops
 */
class CreateFileLocks extends BaseOps {

	/**
	 * @throws \Exception
	 */
	public function create() :bool {
		$pathsProcessed = false;
		foreach ( $this->file->getExistingPossiblePaths() as $path ) {
			$theLock = null;
			foreach ( $this->getFileLocks() as $maybeLock ) {
				if ( $maybeLock->file === $path ) {
					$theLock = $maybeLock;
					break;
				}
			}
			if ( !$theLock instanceof FileLocker\EntryVO ) {
				$this->processPath( $path );
				$pathsProcessed = true;
			}
		}
		return $pathsProcessed;
	}

	/**
	 * @param string $path
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
			$success = $inserter->insert( $entry );

			$this->clearFileLocksCache();
		}
	}
}