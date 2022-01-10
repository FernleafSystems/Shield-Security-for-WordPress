<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Services\Services;

class ValidateLoginIntentRequest {

	use MfaControllerConsumer;

	/**
	 * @throws \Exception
	 */
	public function run() :bool {
		$mfaCon = $this->getMfaCon();
		/** @var LoginGuard\Options $opts */
		$opts = $mfaCon->getOptions();

		$user = Services::WpUsers()->getCurrentWpUser();
		if ( !$user instanceof \WP_User ) {
			throw new \Exception( 'No user logged-in.' );
		}

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

		if ( !$validated  ) {
			$countSuccessful = count( array_filter( $providerStates ) );
			$validated = $opts->isChainedAuth() ? $countSuccessful == count( $providers ) : $countSuccessful > 0;
		}

		if ( $validated ) {
			// Some cleanup can only run if login is completely tested and completely valid.
			foreach ( $successfulProviders as $provider ) {
				$provider->postSuccessActions( $user );
			}
		}

		return $validated;
	}
}