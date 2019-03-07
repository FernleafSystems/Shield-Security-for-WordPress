<?php

use \FernleafSystems\Wordpress\Plugin\Shield\License\EddLicenseVO;
use \FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Edd {

	/**
	 * @var ICWP_WPSF_Edd
	 */
	protected static $oInstance = null;

	/**
	 * @var array
	 */
	private $aAdditionalRequestParams;

	/**
	 * @return ICWP_WPSF_Edd
	 */
	public static function GetInstance() {
		if ( is_null( self::$oInstance ) ) {
			self::$oInstance = new self();
		}
		return self::$oInstance;
	}

	/**
	 * A simple outgoing POST request to see that we can communicate with the ODP servers
	 * @param string $sStoreUrl
	 * @return string
	 */
	public function ping( $sStoreUrl ) {
		$sStoreUrl = add_query_arg( [ 'license_ping' => 'Y' ], $sStoreUrl );
		$aParams = array(
			'body' => array(
				'ping'    => 'pong',
				'license' => 'abcdefghi',
				'item_id' => '123',
				'url'     => Services::WpGeneral()->getWpUrl()
			)
		);

		$oHttpReq = Services::HttpRequest();
		if ( $oHttpReq->post( $sStoreUrl, $aParams ) ) {
			$aResult = @json_decode( $oHttpReq->lastResponse->body, true );
			$sResult = ( isset( $aResult[ 'success' ] ) && $aResult[ 'success' ] ) ? 'success' : 'unknown failure';
		}
		else {
			$sResult = $oHttpReq->lastError->get_error_message();
		}
		return $sResult;
	}

	/**
	 * @param string $sStoreUrl
	 * @param string $sKey
	 * @param string $sItemId
	 * @return \FernleafSystems\Wordpress\Plugin\Shield\License\EddLicenseVO
	 */
	public function activateLicense( $sStoreUrl, $sKey, $sItemId ) {
		return $this->commonLicenseAction( 'activate_license', $sStoreUrl, $sKey, $sItemId );
	}

	/**
	 * @param string $sStoreUrl
	 * @param string $sItemId
	 * @return \FernleafSystems\Wordpress\Plugin\Shield\License\EddLicenseVO
	 */
	public function activateLicenseKeyless( $sStoreUrl, $sItemId ) {
		return $this->activateLicense( $sStoreUrl, '', $sItemId );
	}

	/**
	 * @param string $sStoreUrl
	 * @param string $sKey
	 * @param string $sItemId
	 * @return \FernleafSystems\Wordpress\Plugin\Shield\License\EddLicenseVO|null
	 */
	public function checkLicense( $sStoreUrl, $sKey, $sItemId ) {
		return $this->commonLicenseAction( 'check_license', $sStoreUrl, $sKey, $sItemId );
	}

	/**
	 * @param string $sStoreUrl
	 * @param string $sKey
	 * @param string $sItemId
	 * @return \FernleafSystems\Wordpress\Plugin\Shield\License\EddLicenseVO
	 */
	public function deactivateLicense( $sStoreUrl, $sKey, $sItemId ) {
		return $this->commonLicenseAction( 'deactivate_license', $sStoreUrl, $sKey, $sItemId );
	}

	/**
	 * @param string $sAction
	 * @param string $sStoreUrl
	 * @param string $sKey
	 * @param string $sItemId
	 * @return EddLicenseVO
	 */
	private function commonLicenseAction( $sAction, $sStoreUrl, $sKey, $sItemId ) {
		$oWp = Services::WpGeneral();
		$aLicenseLookupParams = array(
			'timeout' => 60,
			'body'    => array_merge(
				array(
					'edd_action' => $sAction,
					'license'    => $sKey,
					'item_id'    => $sItemId,
					'url'        => $oWp->getHomeUrl(),
					'alt_url'    => $oWp->getWpUrl()
				),
				$this->getRequestParams()
			)
		);

		return ( new EddLicenseVO() )
			->applyFromArray( $this->sendReq( $sStoreUrl, $aLicenseLookupParams, false ) )
			->setLastRequestAt( Services::Request()->ts() );
	}

	/**
	 * first attempts GET, then POST if the GET is successful but the data is not right
	 * @param string $sUrl
	 * @param array  $aArgs
	 * @param bool   $bAsPost
	 * @return array
	 */
	private function sendReq( $sUrl, $aArgs, $bAsPost = false ) {
		$aResponse = array();
		$oHttpReq = Services::HttpRequest();

		if ( $bAsPost ) {
			if ( $oHttpReq->post( $sUrl, $aArgs ) ) {
				$aResponse = empty( $oHttpReq->lastResponse->body ) ? [] : @json_decode( $oHttpReq->lastResponse->body, true );
			}
			return $aResponse;
		}
		else if ( $oHttpReq->get( $sUrl, $aArgs ) ) {
			$aResponse = empty( $oHttpReq->lastResponse->body ) ? [] : @json_decode( $oHttpReq->lastResponse->body, true );
			if ( empty( $aResponse ) ) {
				$aResponse = $this->sendReq( $sUrl, $aArgs, true );
			}
		}

		return $aResponse;
	}

	/**
	 * @param array $aData
	 * @return \FernleafSystems\Wordpress\Plugin\Shield\License\EddLicenseVO
	 */
	public function getLicenseVoFromData( $aData ) {
		return ( new \FernleafSystems\Wordpress\Plugin\Shield\License\EddLicenseVO() )->applyFromArray( $aData );
	}

	/**
	 * @return array
	 */
	public function getRequestParams() {
		return is_array( $this->aAdditionalRequestParams ) ? $this->aAdditionalRequestParams : array();
	}

	/**
	 * @param array $aParams
	 * @return $this
	 */
	public function setRequestParams( $aParams = array() ) {
		$this->aAdditionalRequestParams = is_array( $aParams ) ? $aParams : array();
		return $this;
	}
}