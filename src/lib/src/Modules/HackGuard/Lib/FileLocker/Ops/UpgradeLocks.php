<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

/**
 * If the current cipher is unsupported by both local server and SNAPI, we recreate encrypted content
 * with a mutually supported cipher, then update the DB record.
 *
 * If the update fails for whatever reason, we just delete the lock record entirely and leave the
 * normal lock (re)creation process to assess as required.
 */
class UpgradeLocks extends BaseOps {

	use ModConsumer;

	public function run() {
		$conFL = self::con()->getModule_HackGuard()->getFileLocker();

		$ciphers = new GetAvailableCiphers();

		$changed = false;
		foreach ( $conFL->getLocks() as $lock ) {

			if ( \in_array( $lock->cipher, $ciphers->full() ) ) {
				continue; // nothing to upgrade.
			}

			$first = $ciphers->firstFull();
			try {
				$publicKey = $this->getPublicKey();
				$raw = ( new BuildEncryptedFilePayload() )->fromContent(
					( new ReadOriginalFileContent() )->run( $lock ),
					\reset( $publicKey ),
					$first
				);

				self::con()
					->db_con
					->dbhFileLocker()
					->getQueryUpdater()
					->updateRecord( $lock, [
						'content'       => \base64_encode( $raw ),
						'public_key_id' => \key( $publicKey ),
						'cipher'        => $first,
						'updated_at'    => Services::Request()->ts(),
					] );

				$changed = true;
			}
			catch ( \Exception $e ) {
				( new DeleteFileLock() )->delete( $lock );
				$changed = true;
			}
		}

		if ( $changed ) {
			$conFL->canEncrypt( true );
			$conFL->clearLocks();
		}
	}
}