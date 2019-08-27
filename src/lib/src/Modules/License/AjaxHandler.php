<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class AjaxHandler extends Shield\Modules\Base\AjaxHandlerShield {

	/**
	 * @param string $sAction
	 * @return array
	 */
	protected function processAjaxAction( $sAction ) {

		switch ( $sAction ) {
			case 'license_handling':
				$aResponse = $this->ajaxExec_LicenseHandling();
				break;
			case 'connection_debug':
				$aResponse = $this->ajaxExec_ConnectionDebug();
				break;

			default:
				$aResponse = parent::processAjaxAction( $sAction );
		}

		return $aResponse;
	}

	/**
	 * @return array
	 */
	private function ajaxExec_ConnectionDebug() {
		/** @var \ICWP_WPSF_FeatureHandler_License $oMod */
		$oMod = $this->getMod();
		$bSuccess = false;

		$oHttpReq = Services::HttpRequest()
							->request(
								add_query_arg( [ 'license_ping' => 'Y' ], $oMod->getLicenseStoreUrl() ),
								[
									'body' => [ 'ping' => 'pong' ]
								],
								'POST'
							);

		if ( !$oHttpReq->isSuccess() ) {
			$sResult = implode( '; ', $oHttpReq->lastError->get_error_messages() );
		}
		else if ( !empty( $oHttpReq->lastResponse->body ) ) {
			$aResult = @json_decode( $oHttpReq->lastResponse->body, true );
			if ( isset( $aResult[ 'success' ] ) && $aResult[ 'success' ] ) {
				$bSuccess = true;
				$sResult = 'Successful - no problems detected communicating with license server.';
			}
			else {
				$sResult = 'Unknown failure due to unexpected response.';
			}
		}
		else {
			$sResult = 'Unknown error as we could not get a response back from the server.';
		}

		return [
			'success' => $bSuccess,
			'message' => $sResult
		];
	}

	/**
	 * @return array
	 */
	private function ajaxExec_LicenseHandling() {
		/** @var \ICWP_WPSF_FeatureHandler_License $oMod */
		$oMod = $this->getMod();

		$bSuccess = false;
		$sMessage = 'Unsupported license action';

		$sLicenseAction = Services::Request()->post( 'license-action' );

		if ( $sLicenseAction == 'clear' ) {
			$bSuccess = true;
			$oMod->deactivate( 'cleared' );
			$oMod->clearLicenseData();
			$sMessage = __( 'Success', 'wp-simple-firewall' ).'! '
						.__( 'Reloading page', 'wp-simple-firewall' ).'...';
		}
		else if ( $sLicenseAction == 'check' ) {

			$nCheckInterval = $oMod->getLicenseNotCheckedForInterval();
			if ( $nCheckInterval < 20 ) {
				$nWait = 20 - $nCheckInterval;
				$sMessage = sprintf(
					__( 'Please wait %s before attempting another license check.', 'wp-simple-firewall' ),
					sprintf( _n( '%s second', '%s seconds', $nWait, 'wp-simple-firewall' ), $nWait )
				);
			}
			else {
				$bSuccess = $oMod->verifyLicense( true )
								 ->hasValidWorkingLicense();
				$sMessage = $bSuccess ? __( 'Valid license found.', 'wp-simple-firewall' ) : __( "Valid license couldn't be found.", 'wp-simple-firewall' );
			}
		}

		return [
			'success' => $bSuccess,
			'message' => $sMessage,
		];
	}
}