<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Licenses\Keyless;

class AjaxHandler extends Shield\Modules\BaseShield\AjaxHandler {

	protected function processAjaxAction( string $action ) :array {

		switch ( $action ) {
			case 'license_handling':
				$response = $this->ajaxExec_LicenseHandling();
				break;
			case 'connection_debug':
				$response = $this->ajaxExec_ConnectionDebug();
				break;

			default:
				$response = parent::processAjaxAction( $action );
		}

		return $response;
	}

	private function ajaxExec_ConnectionDebug() :array {

		$success = ( new Keyless\Ping() )->ping();
		$host = wp_parse_url( Keyless\Base::DEFAULT_URL_STUB, PHP_URL_HOST );

		if ( $success ) {
			$msg = 'Successfully connected to license server.';
		}
		elseif ( !Services::IP()->isValidIp( gethostbyname( $host ) ) ) {
			$msg = sprintf( 'Could not resolve host IP address: %s', $host );
		}
		else {
			$msg = 'Failed to connect to license server.';
		}

		return [
			'success' => $success,
			'message' => $msg
		];
	}

	private function ajaxExec_LicenseHandling() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$licHandler = $mod->getLicenseHandler();

		$success = false;
		$msg = 'Unsupported license action';

		$sLicenseAction = Services::Request()->post( 'license-action' );

		if ( $sLicenseAction == 'clear' ) {
			$success = true;
			$licHandler->deactivate( false );
			$licHandler->clearLicense();
			$msg = __( 'Success', 'wp-simple-firewall' ).'! '
				   .__( 'Reloading page', 'wp-simple-firewall' ).'...';
		}
		elseif ( $sLicenseAction == 'check' ) {

			$nCheckInterval = $licHandler->getLicenseNotCheckedForInterval();
			if ( $nCheckInterval < 20 ) {
				$nWait = 20 - $nCheckInterval;
				$msg = sprintf(
					__( 'Please wait %s before attempting another license check.', 'wp-simple-firewall' ),
					sprintf( _n( '%s second', '%s seconds', $nWait, 'wp-simple-firewall' ), $nWait )
				);
			}
			else {
				try {
					$success = $licHandler->verify( true )
										  ->hasValidWorkingLicense();
					$msg = $success ? __( 'Valid license found.', 'wp-simple-firewall' ) : __( "Valid license couldn't be found.", 'wp-simple-firewall' );
				}
				catch ( \Exception $e ) {
					$msg = $e->getMessage();
				}
			}
		}

		return [
			'success' => $success,
			'message' => $msg,
		];
	}
}