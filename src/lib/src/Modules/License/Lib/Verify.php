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
		$licHandler = $mod->getLicenseHandler();

		$this->preVerify();

		$existing = $licHandler->getLicense();

		$license = ( new LookupRequest() )
			->setMod( $mod )
			->lookup();

		$isSuccessfulApiRequest = false;

		if ( $license->isValid() ) {
			$isSuccessfulApiRequest = true;
			$existing = $license;
			$existing->updateLastVerifiedAt( true );
			if ( !$licHandler->isActive() ) {
				$opts->setOptAt( 'license_activated_at' );
			}
			$mod->clearLastErrors();
			$opts->setOpt( 'license_data', $existing->getRawData() ); // need to do this before event
			$this->getCon()->fireEvent( 'lic_check_success' );

			// Migrate to newer Site Install ID
			$newerInstallID = $con->getInstallationID()[ 'id' ];
			if ( $newerInstallID != $con->getSiteInstallationId() ) {
				Services::WpGeneral()->updateOption( $con->prefixOption( 'install_id' ), $newerInstallID );
			}
		}
		elseif ( $license->isReady() ) {
			$isSuccessfulApiRequest = true;
			// License lookup failed but request was successful - so use what we get
			$licHandler->deactivate();
			$existing = $licHandler->getLicense();
		}
		elseif ( $existing->isReady() ) { // Has a stored license but license HTTP request failed

			$mod->setLastErrors( [
				__( 'The most recent request to verify the site license encountered a problem.', 'wp-simple-firewall' )
			] );

			if ( Services::Request()->ts() > $licHandler->getRegistrationExpiresAt() ) {
				$licHandler->deactivate();
				$existing = $licHandler->getLicense();
			}
			elseif ( $licHandler->isLastVerifiedExpired() ) {
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
			$licHandler->clearLicense();
			$existing = $licHandler->getLicense();
		}

		$existing->last_request_at = Services::Request()->ts();
		$opts->setOpt( 'license_data', $existing->getRawData() );
		$this->getMod()->saveModOptions();

		if ( !$isSuccessfulApiRequest ) {
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
