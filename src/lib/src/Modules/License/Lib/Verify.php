<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\License\ShieldLicense;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\License\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\HandshakingNonce;
use FernleafSystems\Wordpress\Services\Services;

class Verify {

	use ModConsumer;

	/**
	 * @throws \Exception
	 */
	public function run() {
		$mod = $this->mod();
		$opts = $this->opts();
		$licHandler = $mod->getLicenseHandler();

		$this->preVerify();

		$existing = $licHandler->getLicense();

		$license = $this->sendRequest();

		$isSuccessfulApiRequest = false;

		if ( $license->isValid() ) {
			$isSuccessfulApiRequest = true;
			$existing = $license;
			$existing->updateLastVerifiedAt( true );
			if ( !$licHandler->isActive() ) {
				$opts->setOptAt( 'license_activated_at' );
			}
			$mod->clearLastErrors();
			$licHandler->updateLicenseData( $existing->getRawData() ); // need to do this before event
			$this->con()->fireEvent( 'lic_check_success' );
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
				( new LicenseEmails() )->sendLicenseWarningEmail();
			}
		}
		else { // all else fails, clear any license details entirely
			$licHandler->clearLicense();
			$existing = $licHandler->getLicense();
		}

		$existing->last_request_at = Services::Request()->ts();
		$licHandler->updateLicenseData( $existing->getRawData() );
		$this->mod()->saveModOptions();

		if ( !$isSuccessfulApiRequest ) {
			throw new \Exception( 'License API HTTP Request Failed.' );
		}
	}

	private function preVerify() {
		Services::WpFs()->touch( $this->con()->paths->forFlag( 'license_check' ) );
		$this->opts()->setOptAt( 'license_last_checked_at' );
		$this->mod()->saveModOptions();
	}

	private function sendRequest() :ShieldLicense {
		$lookup = new Lookup();
		$lookup->url = $this->opts()->getMasterSiteLicenseURL();
		$lookup->install_ids = [
			'shieldpro' => $this->con()->getInstallationID()[ 'id' ],
		];
		$lookup->nonce = ( new HandshakingNonce() )->create();
		$lookup->meta = [
			'version_shield' => $this->con()->getVersion(),
			'version_php'    => Services::Data()->getPhpVersionCleaned()
		];
		return ( new ShieldLicense() )->applyFromArray( $lookup->lookup()[ 'shieldpro' ] ?? [] );
	}
}
