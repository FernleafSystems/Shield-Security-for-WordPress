<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Utility;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Exceptions\PublicKeyRetrievalFailure;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\FileLocker\GetPublicKey;

class RetrievePublicKey {

	use PluginControllerConsumer;

	/**
	 * @throws PublicKeyRetrievalFailure
	 */
	public function retrieve() :array {
		$key = $this->buildGetter()->retrieve();
		if ( !\is_array( $key ) || \count( $key ) !== 1 ) {
			throw new PublicKeyRetrievalFailure( __( 'Failed to obtain public key from API.', 'wp-simple-firewall' ) );
		}

		$keyID = \array_key_first( $key );
		$thePublicKey = \reset( $key );
		if ( !\is_int( $keyID ) || $keyID < 1 || !\is_string( $thePublicKey ) || \trim( $thePublicKey ) === '' ) {
			throw new PublicKeyRetrievalFailure( __( 'Public key was empty.', 'wp-simple-firewall' ) );
		}

		return $key;
	}

	protected function buildGetter() :GetPublicKey {
		return new GetPublicKey();
	}
}
