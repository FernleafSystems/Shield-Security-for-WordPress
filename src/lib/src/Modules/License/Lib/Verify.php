<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\InstallationID;
use FernleafSystems\Wordpress\Plugin\Shield\License\ShieldLicense;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\HandshakingNonce;
use FernleafSystems\Wordpress\Services\Services;

class Verify {

	use PluginControllerConsumer;

	/**
	 * @throws \Exception
	 */
	public function run() {
		$licHandler = self::con()->comps->license;

		$wasLicenseActive = $licHandler->isActive();
		$licenseLookupSuccess = false;

		$this->preVerify();

		try {
			$license = $this->sendRequest();

			if ( $license->isValid() ) {
				$existing = $license;
				$existing->updateLastVerifiedAt( true );
				if ( !$wasLicenseActive ) {
					self::con()->opts->optSet( 'license_activated_at', Services::Request()->ts() );
				}
				$licenseLookupSuccess = true;
			}
			else {
				// License lookup failed but request was successful - so use what we get
				if ( $license->isReady() ) {
					self::con()->fireEvent( 'lic_check_fail', [
						'audit_params' => [
							'type' => 'verification'
						]
					] );
				}
				$licHandler->deactivate();
				$existing = $licHandler->getLicense();
			}

			$existing->last_request_at = Services::Request()->ts();

			// need to do this before event
			$licHandler->updateLicenseData( $existing->getRawData() );

			if ( $licenseLookupSuccess ) {
				self::con()->fireEvent( 'lic_check_success' );
			}

			self::con()->opts->store();
		}
		catch ( Exceptions\FailedLicenseRequestHttpException $e ) {

			self::con()->fireEvent( 'lic_check_fail', [
				'audit_params' => [
					'type' => 'HTTP'
				]
			] );

			$licHandler->maybeDeactivateWithGrace();

			throw new \Exception( 'License API HTTP Request Failed.' );
		}
	}

	private function preVerify() {
		Services::WpFs()->touch( self::con()->paths->forFlag( 'license_check' ) );
		self::con()
			->opts
			->optSet( 'license_last_checked_at', Services::Request()->ts() )
			->store();
	}

	/**
	 * @throws Exceptions\FailedLicenseRequestHttpException
	 */
	private function sendRequest() :ShieldLicense {
		$lookup = new Lookup();
		$lookup->url = apply_filters( 'shield/master_site_license_url', self::con()->comps->license->activationURL() );
		$lookup->install_ids = [
			'shieldpro' => ( new InstallationID() )->id(),
		];
		$lookup->nonce = ( new HandshakingNonce() )->create();
		$lookup->meta = [
			'version_shield' => self::con()->cfg->version(),
			'version_php'    => Services::Data()->getPhpVersionCleaned()
		];

		$data = $lookup->lookup()[ 'shieldpro' ] ?? [];
		$data[ 'last_request_at' ] = Services::Request()->ts();
		/** critical **/

		return ( new ShieldLicense() )->applyFromArray( $data );
	}
}
