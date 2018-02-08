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
			$sExpiresAt = date( $oWp->getDateFormat().' '.$oWp->getTimeFormat(), $oWp->getTimeAsGmtOffset( $this->getLicenseExpiresAt() ) );
		}
		else {
			$sExpiresAt = 'n/a';
		}

		$sCheckedAt = date( $oWp->getDateFormat().' '.$oWp->getTimeFormat(), $oWp->getTimeAsGmtOffset( $this->getLicenseLastCheckedAt() ) );

		$aData = array(
			'vars'      => array(
				'product_name'    => $this->getLicenseItemName(),
				'license_active'  => $this->hasValidWorkingLicense() ? 'Active' : 'Not Active',
				'license_status'  => $this->getOfficialLicenseStatus(),
				'license_key'     => $this->hasLicenseKey() ? $this->getLicenseKey() : 'n/a',
				'license_expires' => $sExpiresAt,
				'license_email'   => $this->getOfficialLicenseRegisteredEmail(),
				'last_checked'    => $sCheckedAt,
				'last_errors'     => $this->hasLastErrors() ? $this->getLastErrors() : 'n/a'
			),
			'inputs'    => array(
				'license_key' => array(
					'name'      => $this->prefixOptionKey( 'license_key' ),
					'maxlength' => $this->getDefinition( 'license_key_length' ),
				)
			),
			'ajax_vars' => $this->getBaseAjaxActionRenderData( 'LicenseHandling' ),
			'aHrefs'    => array(
				'shield_pro_url'           => 'http://icwp.io/shieldpro',
				'shield_pro_more_info_url' => 'http://icwp.io/shld1',
				'iframe_url'               => $this->getDefinition( 'landing_page_url' ),
			),
			'flags'     => array(
				'has_license_key'        => $this->isLicenseKeyValidFormat(),
				'show_summary'           => false,
				'show_ads'               => false,
				'button_enabled_recheck' => $this->isLicenseKeyValidFormat(),
				'button_enabled_remove'  => $this->isLicenseKeyValidFormat(),
				'show_standard_options'  => false,
				'show_alt_content'       => true,
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
	protected function storeLicense( $sLicenseKey, $oLicense ) {
		if ( !( $oLicense instanceof ICWP_EDD_LicenseVO ) ) {
			throw new Exception( sprintf( 'Attempt to store something that is not even a license: %s', gettype( $oLicense ) ) );
		}
		else if ( !$oLicense->isSuccess() || $oLicense->getLicenseStatus() != 'valid' ) {
			throw new Exception( 'Attempt to store invalid license.' );
		}

		$nRequestTime = $this->loadDataProcessor()->time();

		$sPreviousKey = $this->getLicenseKey();
		$bLicenseWasValid = $this->hasValidWorkingLicense();

		$this->setOpt( 'license_key', $sLicenseKey )
			 ->setOpt( 'license_expires_at', $oLicense->getExpiresAt() )
			 ->setOpt( 'license_last_checked_at', $nRequestTime )
			 ->setOpt( 'license_official_status', $oLicense->getLicenseStatus() )
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

		$sLicenseAction = $oDp->FetchPost( 'license-action' );

		if ( $sLicenseAction == 'recheck' ) {
			$this->validateCurrentLicenseKey();
			$bSuccess = $this->hasValidWorkingLicense();
		}
		else if ( $sLicenseAction == 'activate' ) {
			$sKey = $oDp->FetchPost( $this->prefixOptionKey( 'license_key' ) );
			$this->activateOfficialLicense( $sKey );
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
		$oLicense = $this->activateOfficialLicense( $this->getLicenseKey(), true );
		if ( is_null( $oLicense ) || !$oLicense->isSuccess() ) {
			$this->deactivate();
		}
	}

//	protected function validateLicenseKey( $sKey ) {
//		$nRequestTime = $this->loadDataProcessor()->time();
//
//		$bCurrentLicenseValid = $this->isOfficialLicenseStatusValid() && !$this->isLastCheckExpired();
//		$sErrorMessage = '';
//
//		$oLicense = $this->activateOfficialLicense( $sKey );
//
//		if ( is_null( $oLicense ) ) {
//			$sErrorMessage = 'Could not successfully request license server.'; // error for license lookup
//		}
//		else if ( !$oLicense->isReady() ) {
//			$sErrorMessage = 'Unexpected response from license server.';
//		}
//		else if ( $oLicense->isReady() ) {
//
//			$bLicenseWasValid = $this->isLicenseActive();
//
//			if ( $oLicense->getLicenseStatus() == 'valid' ) {
//				$this->setOpt( 'license_expires_at', $oLicense->getExpiresAt() )
//					 ->setOpt( 'license_last_checked_at', $nRequestTime )
//					 ->setOfficialLicenseRegisteredEmail( $oLicense->getCustomerEmail() );
//			}
//			$this->setOpt( 'license_official_status', $oLicense->getLicenseStatus() );
//
//			$bCurrentLicenseValid = $this->isOfficialLicenseStatusValid() && !$this->isLicenseExpired();
//
//			$bNewlyActivated = !$bLicenseWasValid && $bCurrentLicenseValid;
//			$bNewlyDeactivated = $bLicenseWasValid && !$bCurrentLicenseValid;
//
//			if ( $bNewlyActivated || !$this->isLicenseActive() ) {
//				$this->setOpt( 'license_activated_at', $nRequestTime );
//			}
//			else if ( $bNewlyDeactivated ) {
//				$sErrorMessage = sprintf( 'Official license check returned as %s.', $oLicense->getLicenseStatus() );
//			}
//		}
//
//		if ( !$bCurrentLicenseValid ) {
//			$this->deactivate( $sErrorMessage );
//		}
//	}

	/**
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
			$bIsShieldCentral = false;

			if ( $bForceUpdate || $bIsNewKey || !$bIsOrigValid ) {
				$oEDD = $this->loadEdd();
				$sPing = $oEDD->ping( $this->getLicenseStoreUrl() );

				if ( $sPing == 'success' ) {
					$oLicense = $oEDD->activateLicense(
						$this->getLicenseStoreUrl(),
						$sKey,
						$this->getLicenseItemId()
					);

					if ( !is_null( $oLicense ) ) {

						if ( !$oLicense->isSuccess() ) {
							$oScLicense = $this->activateOfficialLicenseAsShieldCentral( $sKey );
							if ( $oScLicense->isSuccess() ) {
								$bIsShieldCentral = true;
								$oLicense = $oScLicense;
							}
						}
					}
					else {
						$sErrorMessage = 'Could not successfully request license server.'; // error for license lookup
					}

					try {
						$this->storeLicense( $sKey, $oLicense );
						$this->setOpt( 'is_license_shield_central', $bIsShieldCentral );
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
	 * @param string $sKey
	 * @return ICWP_EDD_LicenseVO
	 */
	protected function activateOfficialLicenseAsShieldCentral( $sKey ) {
		return $this->loadEdd()
					->activateLicense(
						$this->getLicenseStoreUrl(),
						$sKey,
						$this->getLicenseItemIdShieldCentral()
					);
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
		return $this->getDefinition( 'license_item_id' );
	}

	/**
	 * @return string
	 */
	public function getLicenseItemIdShieldCentral() {
		return $this->getDefinition( 'license_item_id_sc' );
	}

	/**
	 * @return string
	 */
	public function getLicenseItemName() {
		return $this->getOpt( 'is_license_shield_central' ) ?
			$this->getDefinition( 'license_item_name_sc' ) :
			$this->getDefinition( 'license_item_name' );
	}

	/**
	 * @return string
	 */
	public function getLicenseStoreUrl() {
		return $this->getDefinition( 'license_store_url' );
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
		return ( $this->getLicenseExpiresAt() < $this->loadDataProcessor()->GetRequestTime() );
	}

	/**
	 * Expires between 2 and 3 days.
	 * @return bool
	 */
	protected function isLastCheckExpired() {
		return ( $this->loadDP()->time() - $this->getLicenseLastCheckedAt()
				 > $this->getDefinition( 'license_lack_check_expire_days' )*DAY_IN_SECONDS*( mt_rand( 20, 30 )/10 ) );
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
		return $this->isLicenseKeyValidFormat() && $this->isOfficialLicenseStatusValid()
			   && $this->isLicenseActive() && !$this->isLicenseExpired();
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
			switch ( $this->getDefinition( 'license_key_type' ) ) {
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

		switch ( $this->getDefinition( 'license_key_type' ) ) {
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
			switch ( $this->getDefinition( 'license_key_type' ) ) {
				case 'alphanumeric':
				default:
					$this->setOpt( 'license_key', preg_replace( '#[^a-z0-9]#i', '', $sLicKey ) );
					break;
			}
		}
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