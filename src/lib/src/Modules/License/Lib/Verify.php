<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\License;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class Verify {

	use ModConsumer;

	public function run() {
		$oCon = $this->getCon();
		/** @var \ICWP_WPSF_FeatureHandler_License $oMod */
		$oMod = $this->getMod();
		/** @var License\Options $oOpts */
		$oOpts = $this->getOptions();
		$oHandler = $oMod->getLicenseHandler();

		$this->preVerify();

		$oExisting = $oHandler->getLicense();

		$oLookupLicense = ( new LookupRequest() )
			->setMod( $oMod )
			->lookup();

		if ( $oLookupLicense->isValid() ) {
			$oExisting = $oLookupLicense;
			$oExisting->updateLastVerifiedAt( true );
			if ( !$oHandler->isActive() ) {
				$oOpts->setOptAt( 'license_activated_at' );
			}
			$oMod->clearLastErrors();
			$oOpts->setOpt( 'license_data', $oExisting->getRawDataAsArray() ); // need to do this before event
			$oCon->fireEvent( 'lic_check_success' );
		}
		elseif ( $oLookupLicense->isReady() ) {
			// License lookup failed but request was successful - so use what we get
			$oHandler->deactivate();
			$oExisting = $oHandler->getLicense();
		}
		elseif ( $oExisting->isReady() ) { // Has a stored license but license HTTP request failed

			if ( Services::Request()->ts() > $oHandler->getRegistrationExpiresAt() ) {
				$oHandler->deactivate();
				$oExisting = $oHandler->getLicense();
			}
			elseif ( $oHandler->isLastVerifiedExpired() ) {
				/**
				 * At this stage we have a license stored, but we couldn't
				 * verify it, but we're within the grace period for checking.
				 *
				 * We don't remove the license yet, but we warn the user
				 */
				( new LicenseEmails() )
					->setMod( $oMod )
					->sendLicenseWarningEmail();
			}
		}
		else { // all else fails, clear any license details entirely
			$oHandler->clearLicense();
			$oExisting = $oHandler->getLicense();
		}

		$oExisting->last_request_at = Services::Request()->ts();
		$oOpts->setOpt( 'license_data', $oExisting->getRawDataAsArray() );
		$this->getMod()->saveModOptions();
	}

	private function preVerify() {
		/** @var License\Options $oOpts */
		$oOpts = $this->getOptions();
		Services::WpFs()->touch( $this->getCon()->getPath_Flags( 'license_check' ) );
		$oOpts->setOptAt( 'license_last_checked_at' );
		$this->getMod()->saveModOptions();
	}
}
