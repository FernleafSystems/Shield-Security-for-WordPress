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

	/**
	 * @return array
	 */
	private function ajaxExec_ConnectionDebug() {
		$oIP = Services::IP();

		$oPing = new Keyless\Ping();
		$oPing->lookup_url_stub = $this->getOptions()->getDef( 'license_store_url_api' );
		$bSuccess = $oPing->ping();

		$sHost = wp_parse_url( $oPing->lookup_url_stub, PHP_URL_HOST );

		if ( $bSuccess ) {
			$sMessage = 'Successfully connected to license server.';
		}
		elseif ( !$oIP->isValidIp( gethostbyname( $sHost ) ) ) {
			$sMessage = sprintf( 'Could not resolve host IP address: %s', $sHost );
		}
		else {
			$sMessage = 'Failed to connect to license server.';
		}
		return [
			'success' => $bSuccess,
			'message' => $sMessage
		];
	}

	/**
	 * @return array
	 */
	private function ajaxExec_LicenseHandling() {
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