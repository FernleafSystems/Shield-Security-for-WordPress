<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\License;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class Verify {

	use ModConsumer;

	/**
	 * @throws \Exception
	 */
	public function run() {
		$con = $this->getCon();
		/** @var License\ModCon $mod */
		$mod = $this->getMod();
		/** @var License\Options $opts */
		$opts = $this->getOptions();
		$oHandler = $mod->getLicenseHandler();

		$this->preVerify();

		$oExisting = $oHandler->getLicense();

		$oLookupLicense = ( new LookupRequest() )
			->setMod( $mod )
			->lookup();

		$bSuccessfulApiRequest = false;

		if ( $oLookupLicense->isValid() ) {
			$bSuccessfulApiRequest = true;
			$oExisting = $oLookupLicense;
			$oExisting->updateLastVerifiedAt( true );
			if ( !$oHandler->isActive() ) {
				$opts->setOptAt( 'license_activated_at' );
			}
			$mod->clearLastErrors();
			$opts->setOpt( 'license_data', $oExisting->getRawDataAsArray() ); // need to do this before event
			$con->fireEvent( 'lic_check_success' );
		}
		elseif ( $oLookupLicense->isReady() ) {
			$bSuccessfulApiRequest = true;
			// License lookup failed but request was successful - so use what we get
			$oHandler->deactivate();
			$oExisting = $oHandler->getLicense();
		}
		elseif ( $oExisting->isReady() ) { // Has a stored license but license HTTP request failed

			$mod->setLastErrors( [
				__( 'The most recent request to verify the site license encountered a problem.', 'wp-simple-firewall' )
			] );

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
					->setMod( $mod )
					->sendLicenseWarningEmail();
			}
		}
		else { // all else fails, clear any license details entirely
			$oHandler->clearLicense();
			$oExisting = $oHandler->getLicense();
		}

		$oExisting->last_request_at = Services::Request()->ts();
		$opts->setOpt( 'license_data', $oExisting->getRawDataAsArray() );
		$this->getMod()->saveModOptions();

		if ( !$bSuccessfulApiRequest ) {
			throw new \Exception( 'License API HTTP Request Failed.' );
		}
	}

	private function preVerify() {
		/** @var License\Options $opts */
		$opts = $this->getOptions();
		Services::WpFs()->touch( $this->getCon()->paths->forFlag( 'license_check' ) );
		$opts->setOptAt( 'license_last_checked_at' );
		$this->getMod()->saveModOptions();
	}
}
