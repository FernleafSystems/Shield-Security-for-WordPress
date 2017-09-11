<?php

if ( class_exists( 'ICWP_WPSF_FeatureHandler_License', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'base_wpsf.php' );

class ICWP_WPSF_FeatureHandler_License extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	protected function doPostConstruction() {
		add_filter( $this->getPremiumLicenseFilterName(), array( $this, 'hasValidActiveLicense' ) );
	}

	protected function doExtraSubmitProcessing() {
		if ( $this->getOptionsVo()->getNeedSave() ) {
			$sKey = $this->getLicenseKey();
		}
	}

	protected function checkKey() {
		$bValid = true; // TODO check key request
		/** @var ICWP_EDD_LicenseVO $oLicense */
		if ( $oLicense->isReady() ) {

			$bWasActive = $this->isLicenseActive();

			$this->setOpt( 'license_expires_at', $oLicense->getExpiresAt() )
				 ->setOpt( 'license_last_checked_at', $this->loadDataProcessor()->time() )
				 ->setOpt( 'license_official_status', $oLicense->getLicenseStatus() );

			$bNowActive = $this->isLicenseActive();
			if ( $this->isLicenseActive() ) {

			}
		}
	}

	/**
	 * @return int
	 */
	protected function getLicenseActivatedAt() {
		return $this->getOpt( 'license_activated_at' );
	}

	/**
	 * @return int
	 */
	protected function getLicenseDeactivatedAt() {
		return $this->getOpt( 'license_deactivated_at' );
	}

	/**
	 * @return int
	 */
	protected function getLicenseExpiresAt() {
		return $this->getOpt( 'license_expires_at' );
	}

	/**
	 * @return int
	 */
	protected function getLicenseLastCheckedAt() {
		return $this->getOpt( 'license_last_checked_at' );
	}

	/**
	 * @return string
	 */
	protected function getOfficialLicenseStatus() {
		return $this->getOpt( 'license_official_status' );
	}

	/**
	 * @return bool
	 */
	public function isOfficialLicenseValid() {
		return ( $this->getOfficialLicenseStatus() == 'valid' );
	}

	/**
	 * @return bool
	 */
	protected function isLicenseActive() {
		return ( $this->getLicenseActivatedAt() > 0 )
			   && $this->isOfficialLicenseValid()
			   && ( $this->getLicenseDeactivatedAt() < $this->getLicenseActivatedAt() )
			   && ( $this->getLicenseExpiresAt() > $this->loadDataProcessor()->GetRequestTime() );
	}

	/**
	 * @return bool
	 */
	public function hasValidActiveLicense() {
		return $this->hasValidLicenseKey() && $this->isLicenseActive();
	}

	/**
	 * @return bool
	 */
	public function hasValidLicenseKey() {
		return $this->isLicenseKeyValid( $this->getLicenseKey() );
	}

	/**
	 * @return string
	 */
	public function getLicenseKey() {
		return $this->getOpt( 'license_key' );
	}

	/**
	 * @return string
	 */
	public function getLicenseItemName() {
		return $this->getDefinition( 'license_item_name' );
	}

	/**
	 * @return string
	 */
	public function getLicenseStoreUrl() {
		return $this->getDefinition( 'license_store_url' );
	}

	/**
	 * @param string $sKey
	 * @return bool
	 */
	public function isLicenseKeyValid( $sKey ) {
		$bValid = !empty( $sKey ) && is_string( $sKey )
				  && ( strlen( $sKey ) == $this->getDefinition( 'license_key_length' ) );

		if ( $bValid ) {
			switch ( $this->getDefinition( 'license_key_type' ) ) {
				case 'alphanumeric':
				default:
					$bValid = ( preg_match( '#[^a-z0-9]#i', $sKey ) === 0 );
					break;
			}
		}
		return $bValid;
	}

	/**
	 * @return bool
	 */
	public function getIsMainFeatureEnabled() {
		return true;
	}

	/**
	 */
	protected function doPrePluginOptionsSave() {
		if ( !$this->isLicenseKeyValid( $this->getLicenseKey() ) ) {
			$this->getOptionsVo()->resetOptToDefault( 'license_key' );
		}
	}

	/**
	 * @param array $aOptionsParams
	 * @return array
	 * @throws Exception
	 */
	protected function loadStrings_SectionTitles( $aOptionsParams ) {

		switch ( $aOptionsParams[ 'slug' ] ) {

			case 'section_license_options' :
				$sTitle = _wpsf__( 'License Options' );
				$sTitleShort = _wpsf__( 'License Options' );
				$aSummary = array(
					sprintf( _wpsf__( 'Purpose - %s' ), _wpsf__( 'Activate Shield Pro Extensions.' ) ),
					sprintf( _wpsf__( 'Recommendation - %s' ), _wpsf__( 'TODO.' ) )
				);
				break;

			default:
				throw new Exception( sprintf( 'A section slug was defined but with no associated strings. Slug: "%s".', $aOptionsParams[ 'slug' ] ) );
		}

		$aOptionsParams[ 'title' ] = $sTitle;
		$aOptionsParams[ 'summary' ] = ( isset( $aSummary ) && is_array( $aSummary ) ) ? $aSummary : array();
		$aOptionsParams[ 'title_short' ] = $sTitleShort;
		return $aOptionsParams;
	}

	/**
	 * @param array $aOptionsParams
	 * @return array
	 * @throws Exception
	 */
	protected function loadStrings_Options( $aOptionsParams ) {

		$sKey = $aOptionsParams[ 'key' ];
		switch ( $sKey ) {
			case 'license_key' :
				$sName = _wpsf__( 'License Key' );
				$sSummary = _wpsf__( 'License Key' );
				$sDescription = _wpsf__( 'License Key' );
				break;

			default:
				throw new Exception( sprintf( 'An option has been defined but without strings assigned to it. Option key: "%s".', $sKey ) );
		}

		$aOptionsParams[ 'name' ] = $sName;
		$aOptionsParams[ 'summary' ] = $sSummary;
		$aOptionsParams[ 'description' ] = $sDescription;
		return $aOptionsParams;
	}
}