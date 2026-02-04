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
		$getter = new GetPublicKey();
		$getter->last_error = self::con()->comps->file_locker->getState()[ 'last_error' ] ?? '';

		$key = $getter->retrieve();
		if ( empty( $key ) || !\is_array( $key ) ) {
			throw new PublicKeyRetrievalFailure( __( 'Failed to obtain public key from API.', 'wp-simple-firewall' ) );
		}

		$thePublicKey = \reset( $key );
		if ( empty( $thePublicKey ) || !\is_string( $thePublicKey ) ) {
			throw new PublicKeyRetrievalFailure( __( 'Public key was empty.', 'wp-simple-firewall' ) );
		}

		return $key;
	}
}
