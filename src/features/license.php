<?php

if ( class_exists( 'ICWP_WPSF_FeatureHandler_License', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base_wpsf.php' );

class ICWP_WPSF_FeatureHandler_License extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	protected function doPostConstruction() {
		add_filter( $this->getPremiumLicenseFilterName(), array( $this, 'hasValidWorkingLicense' ), PHP_INT_MAX );
	}

	/**
	 */
	protected function displayModulePage() {
		$oWp = $this->loadWp();

		$nExpiresAt = $this->getLicenseExpiresAt();
		if ( $nExpiresAt > 0 && $nExpiresAt != PHP_INT_MAX ) {
			$sExpiresAt = date( $oWp->getDateFormat(), $oWp->getTimeAsGmtOffset( $this->getLicenseExpiresAt() ) );
		}
		else {
			$sExpiresAt = 'n/a';
		}

		$sCheckedAt = date( $oWp->getDateFormat().' '.$oWp->getTimeFormat(), $oWp->getTimeAsGmtOffset( $this->getLicenseLastCheckedAt() ) );

		$aLicenseTableVars = array(
			'product_name'    => $this->getLicenseItemName(),
			'license_active'  => $this->hasValidWorkingLicense() ? 'Active' : 'Not Active',
			'license_expires' => $sExpiresAt,
			'license_email'   => $this->getOfficialLicenseRegisteredEmail(),
			'last_checked'    => $sCheckedAt,
			'last_errors'     => $this->hasLastErrors() ? $this->getLastErrors() : ''
		);
		if ( !$this->isKeyless() ) {
			$aLicenseTableVars[ 'license_key' ] = $this->hasLicenseKey() ? $this->getLicenseKey() : 'n/a';
		}

		$aData = array(
			'vars'      => $aLicenseTableVars,
			'inputs'    => array(
				'license_key' => array(
					'name'      => $this->prefixOptionKey( 'license_key' ),
					'maxlength' => $this->getDef( 'license_key_length' ),
				)
			),
			'aLicenseAjax' => $this->getBaseAjaxActionRenderData( 'LicenseHandling' ),
			'aHrefs'    => array(
				'shield_pro_url'           => 'http://icwp.io/shieldpro',
				'shield_pro_more_info_url' => 'http://icwp.io/shld1',
				'iframe_url'               => $this->getDef( 'landing_page_url' ),
			),
			'flags'     => array(
				'show_key'              => !$this->isKeyless(),
				'has_license_key'       => $this->isLicenseKeyValidFormat(),
				'show_ads'              => false,
				'button_enabled_check'  => true,
				'button_enabled_remove' => $this->isLicenseKeyValidFormat(),
				'show_standard_options' => false,
				'show_alt_content'      => true,
			),
			'strings'   => $this->getDisplayStrings(),
		);
		$aData[ 'content' ] = array(
			'alt' => $this->loadRenderer( self::getConn()->getPath_Templates() )
						  ->setTemplate( 'snippets/pro.php' )
						  ->setRenderVars( $aData )
						  ->render()
		);
		$this->display( $aData );
	}

	/**
	 * @return array
	 */
	protected function getDisplayStrings() {
		return $this->loadDP()->mergeArraysRecursive(
			parent::getDisplayStrings(),
			array(
				'btn_actions'         => _wpsf__( 'Audit Trail Viewer' ),
				'btn_actions_summary' => _wpsf__( 'Review audit trail logs ' ),

				'product_name'    => _wpsf__( 'Name' ),
				'license_active'  => _wpsf__( 'Active' ),
				'license_status'  => _wpsf__( 'Status' ),
				'license_key'     => _wpsf__( 'Key' ),
				'license_expires' => _wpsf__( 'Expires' ),
				'license_email'   => _wpsf__( 'Owner' ),
				'last_checked'    => _wpsf__( 'Checked' ),
				'last_errors'     => _wpsf__( 'Error' ),
			)
		);
	}

	/**
	 * Used to store a valid license.
	 * @param string             $sLicenseKey
	 * @param ICWP_EDD_LicenseVO $oLicense
	 * @throws Exception
	 */
	protected function storeLicense( $oLicense, $sLicenseKey = '' ) {
		if ( !( $oLicense instanceof ICWP_EDD_LicenseVO ) ) {
			throw new Exception( sprintf( 'Attempt to store something that is not even a license: %s', gettype( $oLicense ) ) );
		}

		$nRequestTime = $this->loadDP()->time();
		$this->setOpt( 'license_last_checked_at', $nRequestTime )
			 ->setOpt( 'license_official_status', $oLicense->getLicenseStatus() );

		if ( !$oLicense->isSuccess() || $oLicense->getLicenseStatus() != 'valid' ) {
			throw new Exception( 'Attempt to store invalid license.' );
		}

		$sPreviousKey = $this->getLicenseKey();
		$bLicenseWasValid = $this->hasValidWorkingLicense();

		$this->setOpt( 'license_key', $sLicenseKey )
			 ->setOpt( 'license_expires_at', $oLicense->getExpiresAt() )
			 ->setOfficialLicenseRegisteredEmail( $oLicense->getCustomerEmail() );

		$bIsNewLicense = $sPreviousKey != $sLicenseKey;
		$bCurrentLicenseValid = $this->isOfficialLicenseStatusValid() && !$this->isLicenseExpired();

		if ( $bIsNewLicense || !$this->isLicenseActive() || ( !$bLicenseWasValid && $bCurrentLicenseValid ) ) {
			$this->setOpt( 'license_activated_at', $nRequestTime );
		}
	}

	protected function adminAjaxHandlers() {
		add_action( $this->prefixWpAjax( 'LicenseHandling' ), array( $this, 'ajaxLicenseHandling' ) );
	}

	public function ajaxLicenseHandling() {
		$bSuccess = false;
		$oDp = $this->loadDataProcessor();

		$sLicenseAction = $oDp->post( 'license-action' );

		if ( $sLicenseAction == 'check' ) {
			$this->validateCurrentLicenseKey();
			$bSuccess = $this->hasValidWorkingLicense();
		}
		else if ( $sLicenseAction == 'remove' ) {
			$oLicense = $this->loadEdd()
							 ->deactivateLicense(
								 $this->getLicenseStoreUrl(),
								 $this->getLicenseKey(),
								 $this->getLicenseItemId()
							 );
			if ( $oLicense ) {
				$bSuccess = $oLicense->isSuccess();
			}
			$this->deactivate( 'User submitted deactivation' );
			$this->setOpt( 'license_key', '' )
				 ->setOpt( 'license_official_status', 'n/a' );
		}

		$this->sendAjaxResponse( $bSuccess );
	}

	/**
	 * @param string $sDeactivatedReason
	 */
	public function deactivate( $sDeactivatedReason = '' ) {

		$this->setOpt( 'license_expires_at', 0 )
			 ->setOpt( 'is_license_shield_central', false )
			 ->setOfficialLicenseRegisteredEmail( 'n/a' );

		if ( $this->isOfficialLicenseStatusValid() ) {
			$this->setOpt( 'license_official_status', 'cleared' );
		}
		if ( $this->isLicenseActive() ) {
			$this->setOpt( 'license_deactivated_at', $this->loadDataProcessor()->time() );
		}
		if ( !empty( $sDeactivatedReason ) ) {
			$this->setOpt( 'license_deactivated_reason', $sDeactivatedReason );
		}
		// force all options to resave i.e. reset premium to defaults.
		add_filter( $this->prefix( 'force_options_resave' ), '__return_true' );
	}

	protected function validateCurrentLicenseKey() {
		$oLicense = $this->activateLicenseKeyless();
		if ( is_null( $oLicense ) || !$oLicense->isSuccess() ) {
			$this->deactivate();
		}
	}

	/**
	 * @return ICWP_EDD_LicenseVO|null
	 */
	public function activateLicenseKeyless() {

		$sPass = wp_generate_password( 16 );

		$this->setKeylessRequestAt()
			 ->setKeylessRequestHash( sha1( $sPass.$this->loadWp()->getHomeUrl() ) )
			 ->savePluginOptions();

		$oLicense = $this->loadEdd()
						 ->setRequestParams( array( 'nonce' => $sPass ) )
						 ->activateLicense( $this->getLicenseStoreUrl(), '', $this->getLicenseItemId() );
		try {
			$this->storeLicense( $oLicense );
			$this->setLastErrors();
		}
		catch ( Exception $oE ) {
			$this->setLastErrors( 'Could not find a valid license' );
		}

		$this->setKeylessRequestAt( 0 )
			 ->setKeylessRequestHash( '' )
			 ->savePluginOptions();
		return $oLicense;
	}

	/**
	 * Used primarily when you have a license key that you want to use.
	 * @param string $sKey
	 * @param bool   $bForceUpdate
	 * @return ICWP_EDD_LicenseVO
	 */
	public function activateOfficialLicense( $sKey, $bForceUpdate = false ) {
		$oLicense = null;
		$sKey = $this->verifyLicenseKeyFormat( $sKey );
		$sErrorMessage = '';

		// i.e. only continue if the keys are different, or, if they're the same only if your license is expired.
		if ( !is_null( $sKey ) ) {

			$sOrigKey = $this->getLicenseKey();
			$bIsNewKey = $sOrigKey != $sKey;
			$bIsOrigValid = $this->hasValidWorkingLicense();
			$bDeactivateOriginal = $bIsNewKey && $bIsOrigValid;

			if ( $bForceUpdate || $bIsNewKey || !$bIsOrigValid ) {
				$oEDD = $this->loadEdd();
				$sPing = $oEDD->ping( $this->getLicenseStoreUrl() );

				if ( $sPing == 'success' ) {
					$oLicense = $oEDD->activateLicense(
						$this->getLicenseStoreUrl(),
						$sKey,
						$this->getLicenseItemId()
					);

					if ( is_null( $oLicense ) ) {
						$sErrorMessage = 'Could not successfully request license server.'; // error for license lookup
					}
					else {

						try {
							$this->storeLicense( $oLicense, $sKey );
							$this->clearLastErrors();
							// We also officially deactivate any existing valid licenses
							if ( $bDeactivateOriginal && $oLicense->isSuccess() ) {
								$this->loadEdd()
									 ->deactivateLicense( $this->getLicenseStoreUrl(), $sOrigKey, $this->getLicenseItemId() );
							}
						}
						catch ( Exception $oE ) {
							$sErrorMessage = $oE->getMessage();
						}
					}
				}
				else {
					$sErrorMessage = $sPing;
				}
			}
		}
		else {
			$sErrorMessage = 'Invalid License Key Format';
		}

		if ( !empty( $sErrorMessage ) ) {
			$this->clearLastErrors()->setLastErrors( $sErrorMessage );
		}

		return $oLicense;
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
	 * @return string
	 */
	public function getLicenseKey() {
		return $this->getOpt( 'license_key' );
	}

	/**
	 * @return string
	 */
	public function hasLicenseKey() {
		return $this->isLicenseKeyValidFormat();
	}

	/**
	 * @return string
	 */
	public function getLicenseItemId() {
		return $this->getDef( 'license_item_id' );
	}

	/**
	 * @return string
	 */
	public function getLicenseItemIdShieldCentral() {
		return $this->getDef( 'license_item_id_sc' );
	}

	/**
	 * @return string
	 */
	public function getLicenseItemName() {
		return $this->isLicenseShieldCentral() ?
			$this->getDef( 'license_item_name_sc' ) :
			$this->getDef( 'license_item_name' );
	}

	/**
	 * @return string
	 */
	public function getLicenseStoreUrl() {
		return $this->getDef( 'license_store_url' );
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
	 * @return string
	 */
	protected function getOfficialLicenseRegisteredEmail() {
		return $this->getOpt( 'license_registered_email' );
	}

	/**
	 * @return bool
	 */
	public function isOfficialLicenseStatusValid() {
		return ( $this->getOfficialLicenseStatus() == 'valid' );
	}

	/**
	 * @return bool
	 */
	public function isLicenseActive() {
		return ( $this->getLicenseActivatedAt() > 0 )
			   && ( $this->getLicenseDeactivatedAt() < $this->getLicenseActivatedAt() );
	}

	/**
	 * @return bool
	 */
	public function isLicenseShieldCentral() {
		return $this->getOpt( 'is_license_shield_central', true );
	}

	/**
	 * @return bool
	 */
	public function isLicenseKeyValidFormat() {
		return !is_null( $this->verifyLicenseKeyFormat( $this->getLicenseKey() ) );
	}

	/**
	 * @return bool
	 */
	protected function isLicenseExpired() {
		return ( $this->getLicenseExpiresAt() < $this->loadDP()->time() );
	}

	/**
	 * We test various data points:
	 * 1) the key is valid format
	 * 2) the official license status is 'valid'
	 * 3) the license is marked as "active"
	 * 4) the license hasn't expired
	 * 5) the time since the last check hasn't expired
	 * @return bool
	 */
	public function hasValidWorkingLicense() {
		return ( $this->isKeyless() || $this->isLicenseKeyValidFormat() )
			   && $this->isOfficialLicenseStatusValid() && $this->isLicenseActive() && !$this->isLicenseExpired();
	}

	/**
	 * @return bool
	 */
	protected function isKeyless() {
		return (bool)$this->getDef( 'keyless' );
	}

	/**
	 * Expires between 2 and 3 days.
	 * @return bool
	 */
	protected function isLastCheckExpired() {
		return ( $this->loadDP()->time() - $this->getLicenseLastCheckedAt()
				 > $this->getDef( 'license_lack_check_expire_days' )*DAY_IN_SECONDS*( mt_rand( 20, 30 )/10 ) );
	}

	/**
	 * @param string $sEmail
	 * @return string
	 */
	protected function setOfficialLicenseRegisteredEmail( $sEmail ) {
		return $this->setOpt( 'license_registered_email', $sEmail );
	}

	/**
	 * @param string $sKey
	 * @return string|null
	 */
	public function verifyLicenseKeyFormat( $sKey ) {
		$sCleanKey = null;

		$sKey = $this->cleanLicenseKey( $sKey );
		$bValid = !empty( $sKey ) && is_string( $sKey )
				  && ( strlen( $sKey ) == $this->getDefinition( 'license_key_length' ) );

		if ( $bValid ) {
			switch ( $this->getDef( 'license_key_type' ) ) {
				case 'alphanumeric':
				default:
					if ( preg_match( '#[^a-z0-9]#i', $sKey ) === 0 ) {
						$sCleanKey = $sKey;
					}
					break;
			}
		}

		return $sCleanKey;
	}

	protected function cleanLicenseKey( $sKey ) {

		switch ( $this->getDef( 'license_key_type' ) ) {
			case 'alphanumeric':
			default:
				$sKey = preg_replace( '#[^a-z0-9]#i', '', $sKey );
				break;
		}

		return $sKey;
	}

	/**
	 * @return boolean
	 */
	public function getIfShowModuleMenuItem() {
		return parent::getIfShowModuleMenuItem() && self::getConn()->isPremiumExtensionsEnabled();
	}

	/**
	 */
	protected function doPrePluginOptionsSave() {
		// clean the key.
		$sLicKey = $this->getLicenseKey();
		if ( strlen( $sLicKey ) > 0 ) {
			switch ( $this->getDef( 'license_key_type' ) ) {
				case 'alphanumeric':
				default:
					$this->setOpt( 'license_key', preg_replace( '#[^a-z0-9]#i', '', $sLicKey ) );
					break;
			}
		}
	}

	/**
	 * @return int
	 */
	public function getKeylessRequestAt() {
		return (int)$this->getOpt( 'keyless_request_at', 0 );
	}

	/**
	 * @return string
	 */
	public function getKeylessRequestHash() {
		return (string)$this->getOpt( 'keyless_request_hash', '' );
	}

	/**
	 * @return bool
	 */
	public function isKeylessHandshakeExpired() {
		return ( $this->loadDP()->time() - $this->getKeylessRequestAt() )
			   > $this->getDef( 'keyless_handshake_expire' );
	}

	/**
	 * @param string $sHash
	 * @return $this
	 */
	public function setKeylessRequestHash( $sHash ) {
		return $this->setOpt( 'keyless_request_hash', $sHash );
	}

	/**
	 * @param int|null $nTime
	 * @return $this
	 */
	public function setKeylessRequestAt( $nTime = null ) {
		$nTime = is_numeric( $nTime ) ? $nTime : $this->loadDP()->time();
		return $this->setOpt( 'keyless_request_at', $nTime );
	}

	/**
	 * Hooked to the plugin's main plugin_shutdown action
	 */
	public function action_doFeatureShutdown() {
		// Automatically validate active licenses if they've expired.
		if ( $this->hasValidWorkingLicense() && $this->isLastCheckExpired() ) {
			$this->validateCurrentLicenseKey();
		}
		parent::action_doFeatureShutdown();
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