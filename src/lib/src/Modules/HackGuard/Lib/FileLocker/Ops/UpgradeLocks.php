<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

/**
 * There are 2 scenarios to upgrading: a change has been detected, or not
 * 1) When the current cipher is an unavailable cipher, just delete the lock and it'll be recreated.
 * 2) If a change has been detected, we'll see if the current cipher is RC4, decrypt the lock and re-encrypt using
 * updated cipher.
 */
class UpgradeLocks extends BaseOps {

	use ModConsumer;

	public function run() {
		$conFL = self::con()->getModule_HackGuard()->getFileLocker();

		$ciphers = new GetAvailableCiphers();
		$first = $ciphers->firstFull();

		$upgraded = false;

		foreach ( $conFL->getLocks() as $lock ) {

			if ( \in_array( $lock->cipher, $ciphers->full() ) ) {
				continue; // nothing to upgrade.
			}

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

				$upgraded = true;
			}
			catch ( \Exception $e ) {
			}
		}

		if ( $upgraded ) {
			$conFL->canEncrypt( true );
		}
	}
}