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

		$wasLicenseActive = $licHandler->isActive();
		$licenseLookupSuccess = false;

		$this->preVerify();

		try {
			$license = $this->sendRequest();

			if ( $license->isValid() ) {
				$existing = $license;
				$existing->updateLastVerifiedAt( true );
				if ( !$wasLicenseActive ) {
					$opts->setOpt( 'license_activated_at', Services::Request()->ts() );
				}
				$mod->clearLastErrors();
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
			$mod->setLastErrors( [
				__( 'The most recent request to verify the site license encountered a problem.', 'wp-simple-firewall' )
			] );

			$licHandler->maybeDeactivateWithGrace();

			throw new \Exception( 'License API HTTP Request Failed.' );
		}
	}

	private function preVerify() {
		Services::WpFs()->touch( self::con()->paths->forFlag( 'license_check' ) );
		$this->opts()->setOpt( 'license_last_checked_at', Services::Request()->ts() );
		self::con()->opts->store();
	}

	/**
	 * @throws Exceptions\FailedLicenseRequestHttpException
	 */
	private function sendRequest() :ShieldLicense {
		$lookup = new Lookup();
		$lookup->url = $this->opts()->getMasterSiteLicenseURL();
		$lookup->install_ids = [
			'shieldpro' => self::con()->getInstallationID()[ 'id' ],
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
