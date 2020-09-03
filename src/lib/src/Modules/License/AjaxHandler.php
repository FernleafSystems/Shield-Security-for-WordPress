<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Licenses\Keyless;

class AjaxHandler extends Shield\Modules\Base\AjaxHandlerShield {

	/**
	 * @param string $action
	 * @return array
	 */
	protected function processAjaxAction( string $action ) :array {

		switch ( $action ) {
			case 'license_handling':
				$aResponse = $this->ajaxExec_LicenseHandling();
				break;
			case 'connection_debug':
				$aResponse = $this->ajaxExec_ConnectionDebug();
				break;

			default:
				$aResponse = parent::processAjaxAction( $action );
		}

		return $aResponse;
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
		/** @var \ICWP_WPSF_FeatureHandler_License $oMod */
		$oMod = $this->getMod();
		$sHandler = $oMod->getLicenseHandler();

		$bSuccess = false;
		$sMessage = 'Unsupported license action';

		$sLicenseAction = Services::Request()->post( 'license-action' );

		if ( $sLicenseAction == 'clear' ) {
			$bSuccess = true;
			$sHandler->deactivate( false );
			$sHandler->clearLicense();
			$sMessage = __( 'Success', 'wp-simple-firewall' ).'! '
						.__( 'Reloading page', 'wp-simple-firewall' ).'...';
		}
		elseif ( $sLicenseAction == 'check' ) {

			$nCheckInterval = $sHandler->getLicenseNotCheckedForInterval();
			if ( $nCheckInterval < 20 ) {
				$nWait = 20 - $nCheckInterval;
				$sMessage = sprintf(
					__( 'Please wait %s before attempting another license check.', 'wp-simple-firewall' ),
					sprintf( _n( '%s second', '%s seconds', $nWait, 'wp-simple-firewall' ), $nWait )
				);
			}
			else {
				try {
					$bSuccess = $sHandler->verify( true )
										 ->hasValidWorkingLicense();
					$sMessage = $bSuccess ? __( 'Valid license found.', 'wp-simple-firewall' ) : __( "Valid license couldn't be found.", 'wp-simple-firewall' );
				}
				catch ( \Exception $oE ) {
					$sMessage = $oE->getMessage();
				}
			}
		}

		return [
			'success' => $bSuccess,
			'message' => $sMessage,
		];
	}
}