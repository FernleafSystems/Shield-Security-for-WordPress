<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Lib\Exceptions\LicenseAlreadyActivatedException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\License\ActivateLicense;
use FernleafSystems\Wordpress\Services\Services;

class Activate {

	use PluginControllerConsumer;

	public const ERR_INVALID_API_KEY = 1001;
	public const ERR_ALREADY_ACTIVATED = 1002;
	public const ERR_NO_LICENSES_AVAILABLE = 1003;
	public const ERR_ACTIVATION_DENIED = 1004;
	public const ERR_UNKNOWN = 1999;

	public static function isAlreadyActivatedErrorCode( int $errorCode ) :bool {
		return $errorCode === self::ERR_ALREADY_ACTIVATED;
	}

	public static function mapApiErrorCodeToReason( int $errorCode ) :string {
		switch ( $errorCode ) {
			case self::ERR_INVALID_API_KEY:
				$reason = 'invalid_api_key';
				break;
			case self::ERR_NO_LICENSES_AVAILABLE:
				$reason = 'no_licenses_available';
				break;
			case self::ERR_ALREADY_ACTIVATED:
				$reason = 'already_activated';
				break;
			case self::ERR_ACTIVATION_DENIED:
				$reason = 'activation_denied';
				break;
			default:
				$reason = 'unknown';
				break;
		}

		return $reason;
	}

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
			$this->processActivationResponse( $this->sendActivationRequest( $apiKey ) );
		}
		catch ( LicenseAlreadyActivatedException $e ) {
			self::con()->comps->events->fireEvent( 'lic_activation_fail', [
				'audit_params' => [ 'error' => $this->buildErrorMessage( self::ERR_ALREADY_ACTIVATED, '' ) ]
			] );
			throw $e;
		}
		catch ( Exceptions\FailedLicenseRequestHttpException $e ) {
			self::con()->comps->events->fireEvent( 'lic_activation_fail', [
				'audit_params' => [ 'error' => 'HTTP Request Failed' ]
			] );
			throw new \Exception( __( 'License activation request failed.', 'wp-simple-firewall' ), self::ERR_UNKNOWN );
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
		return ( new ActivateLicense() )
			->activate(
				$apiKey,
				apply_filters(
					'shield/master_site_license_url',
					self::con()->comps->license->activationURL()
				)
			);
	}

	/**
	 * @throws LicenseAlreadyActivatedException|\Exception
	 */
	private function processActivationResponse( array $response ) :void {
		$errorCode = \is_numeric( $response[ 'error_code' ] ?? null ) ? (int)$response[ 'error_code' ] : self::ERR_UNKNOWN;

		if ( $errorCode === 0 ) {
			self::con()->comps->events->fireEvent( 'lic_activation_success' );
		}
		elseif ( $errorCode === self::ERR_ALREADY_ACTIVATED ) {
			throw new LicenseAlreadyActivatedException();
		}
		else {
			throw new \Exception( $this->buildErrorMessage( $errorCode, (string)( $response[ 'message' ] ?? '' ) ), $errorCode );
		}
	}

	private function buildErrorMessage( int $errorCode, string $apiMessage ) :string {
		switch ( self::mapApiErrorCodeToReason( $errorCode ) ) {
			case 'invalid_api_key':
				$msg = __( 'License activation failed because the API key is invalid.', 'wp-simple-firewall' );
				break;
			case 'no_licenses_available':
				$msg = __( 'License activation failed because no licenses are available on this account.', 'wp-simple-firewall' );
				break;
			case 'already_activated':
				$msg = __( 'This site is already activated for this account.', 'wp-simple-firewall' );
				break;
			case 'activation_denied':
				$msg = __( 'License activation was denied for this site.', 'wp-simple-firewall' );
				break;
			default:
				$msg = !empty( $apiMessage ) ? $apiMessage : __( 'Unknown activation error.', 'wp-simple-firewall' );
				break;
		}

		return $msg;
	}
}
