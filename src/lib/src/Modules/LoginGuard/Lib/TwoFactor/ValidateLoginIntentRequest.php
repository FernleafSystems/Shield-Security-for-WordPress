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
		$aProviders = $oMfaCon->getProvidersForUser( Services::WpUsers()->getCurrentWpUser() );
		if ( empty( $aProviders ) ) {
			throw new \Exception( 'No valid providers' );
		}

		$bValid = false;
		if ( isset( $aProviders[ Provider\Backup::SLUG ] ) ) { // special case.
			$bValid = $aProviders[ Provider\Backup::SLUG ]->validateLoginIntent( $oUser );
			unset( $aProviders[ Provider\Backup::SLUG ] );
		}

		if ( !$bValid ) {
			$aStates = [];
			foreach ( $aProviders as $sSlug => $oProvider ) {
				$aStates[ $sSlug ] = $oProvider->validateLoginIntent( $oUser );
				if ( $aStates[ $sSlug ] && !$oOpts->isChainedAuth() ) {
					break;
				}
			}

			$nSuccessful = count( array_filter( $aStates ) );
			$bValid = $oOpts->isChainedAuth() ? $nSuccessful == count( $aProviders ) : $nSuccessful > 0;
		}

		return $bValid;
	}
}