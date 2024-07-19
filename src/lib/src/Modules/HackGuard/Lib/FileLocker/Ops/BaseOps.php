<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\FileLocker\Ops as FileLockerDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Exceptions\PublicKeyRetrievalFailure;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\FileLocker\GetPublicKey;

class BaseOps {

	use PluginControllerConsumer;

	/**
	 * @var FileLocker\File
	 */
	protected $file;

	protected function findLockRecordForFile() :?FileLockerDB\Record {
		$theLock = null;
		foreach ( $this->file->getPossiblePaths() as $path ) {
			foreach ( ( new LoadFileLocks() )->ofType( $this->file->type ) as $maybeLock ) {
				if ( $maybeLock->path === $path ) {
					$theLock = $maybeLock;
					break;
				}
			}
		}
		return $theLock;
	}

	/**
	 * @throws PublicKeyRetrievalFailure
	 */
	protected function getPublicKey() :array {
		$getter = new GetPublicKey();
		$getter->last_error = self::con()->comps->file_locker->getState()[ 'last_error' ] ?? '';

		$key = $getter->retrieve();
		if ( empty( $key ) || !\is_array( $key ) ) {
			throw new PublicKeyRetrievalFailure( 'Failed to obtain public key from API.' );
		}

		$thePublicKey = \reset( $key );
		if ( empty( $thePublicKey ) || !\is_string( $thePublicKey ) ) {
			throw new PublicKeyRetrievalFailure( 'Public key was empty' );
		}

		return $key;
	}

	/**
	 * @return $this
	 */
	public function setWorkingFile( FileLocker\File $file ) {
		$this->file = $file;
		return $this;
	}
}