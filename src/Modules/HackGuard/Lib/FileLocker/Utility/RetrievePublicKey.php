<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Utility;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Exceptions\PublicKeyRetrievalFailure;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\FileLocker\GetPublicKey;

class RetrievePublicKey {

	/**
	 * @return array<int,string>
	 * @throws PublicKeyRetrievalFailure
	 */
	public function retrieve() :array {
		$key = $this->buildGetter()->retrieve();
		if ( $key === null ) {
			throw new PublicKeyRetrievalFailure( __( 'Failed to obtain public key from API.', 'wp-simple-firewall' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Caught by FileLockerController, UpgradeLocks and ScansFileLockerAction, which log it, store it or replace it with a generic message.
		}

		return $key;
	}

	protected function buildGetter() :GetPublicKey {
		return new GetPublicKey();
	}
}
