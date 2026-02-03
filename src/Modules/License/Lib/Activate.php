<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\License\ActivateLicense;
use FernleafSystems\Wordpress\Services\Services;

class Activate {

	use PluginControllerConsumer;

	/**
	 * Run the license activation with the provided API key.
	 * Throws exception on any failure (validation, HTTP, API rejection).
	 *
	 * @throws \Exception
	 */
	public function run( string $apiKey ) :void {
		if ( empty( $apiKey ) ) {
			throw new \Exception( __( 'API key cannot be empty.', 'wp-simple-firewall' ) );
		}

		$this->preActivate();

		try {
			$result = $this->sendActivationRequest( $apiKey );

			// API uses error_code: 0 for success (consistent with other ShieldNet APIs)
			if ( ( $result[ 'error_code' ] ?? 1 ) === 0 ) {
				self::con()->fireEvent( 'lic_activation_success' );
				return; // Success - activation completed
			}

			// API returned error
			$errorMessage = $result[ 'message' ] ?? __( 'Unknown activation error.', 'wp-simple-firewall' );
			self::con()->fireEvent( 'lic_activation_fail', [
				'audit_params' => [ 'error' => $errorMessage ]
			] );
			throw new \Exception( $errorMessage );
		}
		catch ( Exceptions\FailedLicenseRequestHttpException $e ) {
			self::con()->fireEvent( 'lic_activation_fail', [
				'audit_params' => [ 'error' => 'HTTP Request Failed' ]
			] );
			throw new \Exception( __( 'License activation request failed.', 'wp-simple-firewall' ) );
		}
	}

	private function preActivate() :void {
		Services::WpFs()->touch( self::con()->paths->forFlag( 'license_check' ) );
		self::con()
			->opts
			->optSet( 'license_last_checked_at', Services::Request()->ts() )
			->store();
	}

	/**
	 * @throws Exceptions\FailedLicenseRequestHttpException
	 */
	private function sendActivationRequest( string $apiKey ) :array {
		$activator = new ActivateLicense();
		$activationUrl = apply_filters(
			'shield/master_site_license_url',
			self::con()->comps->license->activationURL()
		);
		return $activator->activate( $apiKey, $activationUrl );
	}
}
