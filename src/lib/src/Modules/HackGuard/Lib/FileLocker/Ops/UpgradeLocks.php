<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\FileLocker\Ops as FileLockerDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Encrypt\OpenSslEncryptVo;

/**
 * If the current cipher is unsupported by both local server and SNAPI, we recreate encrypted content
 * with a mutually supported cipher, then update the DB record.
 *
 * If the update fails for whatever reason, we just delete the lock record entirely and leave the
 * normal lock (re)creation process to assess as required.
 */
class UpgradeLocks extends BaseOps {

	use PluginControllerConsumer;

	public function run() {
		$flCon = self::con()->comps->file_locker;
		$flCon->canEncrypt( true );

		if ( !empty( $flCon->getState()[ 'cipher' ] ) ) {

			$repaired = false;

			foreach ( $flCon->getLocks() as $lock ) {

				$upgradeRequired = false;

				if ( \in_array( $lock->cipher, ( new GetAvailableCiphers() )->full() ) ) {
					// We must also check the stored VO as we're also storing the cipher in there from an earlier bug.
					$decoded = \json_decode( $lock->content, true );
					if ( \is_array( $decoded ) ) {
						$VO = ( new OpenSslEncryptVo() )->applyFromArray( $decoded );
						$upgradeRequired = !\in_array( $VO->cipher, ( new GetAvailableCiphers() )->full() );
					}
				}
				else {
					$upgradeRequired = true;
				}

				if ( $upgradeRequired ) {
					$this->runLockRepair( $lock );
					$repaired = true;
				}
			}

			if ( $repaired ) {
				$flCon->clearLocks();
			}
		}
	}

	private function runLockRepair( FileLockerDB\Record $lock ) :void {
		$flCon = self::con()->comps->file_locker;
		try {
			$cipher = $flCon->getState()[ 'cipher' ];
			$publicKey = $this->getPublicKey();

			self::con()
				->db_con
				->file_locker
				->getQueryUpdater()
				->updateRecord( $lock, [
					'content'       => \base64_encode(
						( new BuildEncryptedFilePayload() )->fromContent(
							( new ReadOriginalFileContent() )->run( $lock ),
							\reset( $publicKey ),
							$cipher
						)
					),
					'public_key_id' => \key( $publicKey ),
					'cipher'        => $cipher,
					'updated_at'    => Services::Request()->ts(),
				] );
		}
		catch ( \Exception $e ) {
			( new DeleteFileLock() )->delete( $lock );
		}
	}
}