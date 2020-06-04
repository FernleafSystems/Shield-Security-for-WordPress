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
	public function run() {
		$oMfaCon = $this->getMfaCon();
		/** @var LoginGuard\Options $oOpts */
		$oOpts = $oMfaCon->getOptions();

		$oUser = Services::WpUsers()->getCurrentWpUser();
		if ( !$oUser instanceof \WP_User ) {
			throw new \Exception( 'No user logged-in.' );
		}
		$aProviders = $oMfaCon->getProvidersForUser( $oUser, true );
		if ( empty( $aProviders ) ) {
			throw new \Exception( 'No valid providers' );
		}

		$aSuccessfulProviders = [];

		$bValidated = false;
		{ // Backup code is special case
			if ( isset( $aProviders[ Provider\Backup::SLUG ] ) ) {
				if ( $aProviders[ Provider\Backup::SLUG ]->validateLoginIntent( $oUser ) ) {
					$bValidated = true;
					$aSuccessfulProviders[] = $aProviders[ Provider\Backup::SLUG ];
				}
				unset( $aProviders[ Provider\Backup::SLUG ] );
			}
		}

		if ( !$bValidated ) {
			$aStates = [];
			foreach ( $aProviders as $sSlug => $oProvider ) {
				$aStates[ $sSlug ] = $oProvider->validateLoginIntent( $oUser );
				if ( $aStates[ $sSlug ] ) {
					$aSuccessfulProviders[] = $oProvider;
				}
			}

			$nSuccessful = count( array_filter( $aStates ) );
			$bValidated = $oOpts->isChainedAuth() ? $nSuccessful == count( $aProviders ) : $nSuccessful > 0;
		}

		if ( $bValidated ) {
			// Some cleanup can only run if login is completely tested and completely valid.
			foreach ( $aSuccessfulProviders as $oProvider ) {
				$oProvider->postSuccessActions( $oUser );
			}
		}

		return $bValidated;
	}
}