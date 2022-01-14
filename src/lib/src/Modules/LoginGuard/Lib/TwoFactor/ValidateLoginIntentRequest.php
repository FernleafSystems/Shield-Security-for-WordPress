<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;

class ValidateLoginIntentRequest {

	use MfaControllerConsumer;

	/**
	 * @throws \Exception
	 */
	public function run( \WP_User $user ) :bool {
		$mfaCon = $this->getMfaCon();
		/** @var LoginGuard\Options $opts */
		$opts = $mfaCon->getOptions();

		if ( !$mfaCon->hasLoginIntent( $user ) ) { // TODO: make this a hash
			throw new \Exception( 'No valid user login intent' );
		}
		$mfaCon->removeLoginIntent( $user );

		$providers = $mfaCon->getProvidersForUser( $user, true );
		if ( empty( $providers ) ) {
			throw new \Exception( 'No valid providers' );
		}

		$providerStates = [];
		$successfulProviders = [];
		foreach ( $providers as $slug => $provider ) {
			$providerStates[ $slug ] = $provider->validateLoginIntent( $user );
			if ( $providerStates[ $slug ] ) {
				$successfulProviders[ $slug ] = $provider;
			}
		}

		$validated = false;

		foreach ( $providers as $slug => $provider ) {
			if ( $provider::BYPASS_MFA ) {
				if ( $providerStates[ $slug ] ) {
					$validated = true;
				}
				unset( $providers[ $slug ] );
				unset( $providerStates[ $slug ] );
			}
		}

		if ( !$validated ) {
			$countSuccessful = count( array_filter( $providerStates ) );
			$validated = $opts->isChainedAuth() ? $countSuccessful == count( $providers ) : $countSuccessful > 0;
		}

		if ( $validated ) {
			// Some cleanup can only run if login is completely tested and completely valid.
			foreach ( $successfulProviders as $provider ) {
				$provider->postSuccessActions( $user );
			}
		}
		else {
			throw new \Exception( 'Could not validate login 2FA' );
		}

		return true;
	}
}