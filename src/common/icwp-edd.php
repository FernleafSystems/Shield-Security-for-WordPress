<?php
if ( class_exists( 'ICWP_WPSF_Edd', false ) ) {
	return;
}

class ICWP_WPSF_Edd extends ICWP_WPSF_Foundation {

	/**
	 * @var ICWP_WPSF_Edd
	 */
	protected static $oInstance = null;

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
	 * @param string $sStoreUrl
	 * @param string $sKey
	 * @param string $sItemId
	 * @return ICWP_EDD_LicenseVO|null
	 */
	public function activateLicense( $sStoreUrl, $sKey, $sItemId ) {
		$oLicense = null;

		$aLicenseLookupParams = array(
			'body' => array(
				'edd_action' => 'activate_license',
				'license'    => $sKey,
				'item_id'    => $sItemId,
				'url'        => $this->loadWp()->getHomeUrl()
			)
		);

		$aContent = $this->loadFS()
						 ->postUrl( $sStoreUrl, $aLicenseLookupParams );
		if ( !empty( $aContent ) ) {
			require_once( dirname( __FILE__ ).'/easydigitaldownloads/ICWP_EDD_LicenseVO.php' );
			$oLicense = new ICWP_EDD_LicenseVO( json_decode( $aContent[ 'body' ] ) );
		}
		return $oLicense;
	}
}