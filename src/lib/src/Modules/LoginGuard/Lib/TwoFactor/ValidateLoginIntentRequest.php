<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider;
use FernleafSystems\Wordpress\Services\Services;

class ValidateLoginIntentRequest {

	use MfaControllerConsumer;

	/**
	 * @return bool
	 * @throws \Exception
	 */
	public function run() :bool {
		$oMfaCon = $this->getMfaCon();
		/** @var LoginGuard\Options $opts */
		$opts = $oMfaCon->getOptions();

		$user = Services::WpUsers()->getCurrentWpUser();
		if ( !$user instanceof \WP_User ) {
			throw new \Exception( 'No user logged-in.' );
		}
		$providers = $oMfaCon->getProvidersForUser( $user, true );
		if ( empty( $providers ) ) {
			throw new \Exception( 'No valid providers' );
		}

		$aSuccessfulProviders = [];

		$validated = false;
		{ // Backup code is special case
			if ( isset( $providers[ Provider\Backup::SLUG ] ) ) {
				if ( $providers[ Provider\Backup::SLUG ]->validateLoginIntent( $user ) ) {
					$validated = true;
					$aSuccessfulProviders[] = $providers[ Provider\Backup::SLUG ];
				}
				unset( $providers[ Provider\Backup::SLUG ] );
			}
		}

		if ( !$validated ) {
			$aStates = [];
			foreach ( $providers as $sSlug => $provider ) {
				$aStates[ $sSlug ] = $provider->validateLoginIntent( $user );
				if ( $aStates[ $sSlug ] ) {
					$aSuccessfulProviders[] = $provider;
				}
			}

			$nSuccessful = count( array_filter( $aStates ) );
			$validated = $opts->isChainedAuth() ? $nSuccessful == count( $providers ) : $nSuccessful > 0;
		}

		if ( $validated ) {
			// Some cleanup can only run if login is completely tested and completely valid.
			foreach ( $aSuccessfulProviders as $provider ) {
				$provider->postSuccessActions( $user );
			}
		}

		return $validated;
	}
}