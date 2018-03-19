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
			$sExpiresAt = $oWp->getTimeStampForDisplay( $this->getLicenseExpiresAt() );
		}
		else {
			$sExpiresAt = 'n/a';
		}

		$sCheckedAt = $oWp->getTimeStampForDisplay( $this->getLicenseVerifiedAt() );

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
			'vars'    => $aLicenseTableVars,
			'inputs'  => array(
				'license_key' => array(
					'name'      => $this->prefixOptionKey( 'license_key' ),
					'maxlength' => $this->getDef( 'license_key_length' ),
				)
			),
			'ajax'    => array(
				'license_handling' => $this->getAjaxActionData( 'license_handling' ),
				'connection_debug' => $this->getAjaxActionData( 'connection_debug' )
			),
			'aHrefs'  => array(
				'shield_pro_url'           => 'http://icwp.io/shieldpro',
				'shield_pro_more_info_url' => 'http://icwp.io/shld1',
				'iframe_url'               => $this->getDef( 'landing_page_url' ),
				'keyless_cp'               => $this->getDef( 'keyless_cp' ),
			),
			'flags'   => array(
				'show_key'              => !$this->isKeyless(),
				'has_license_key'       => $this->isLicenseKeyValidFormat(),
				'show_ads'              => false,
				'button_enabled_check'  => true,
				'button_enabled_remove' => $this->isLicenseKeyValidFormat(),
				'show_standard_options' => false,
				'show_alt_content'      => true,
			),
			'strings' => $this->getDisplayStrings(),
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
	 * @param ICWP_EDD_LicenseVO $oLicense
	 * @return $this
	 * @throws Exception
	 */
	protected function storeLicense( $oLicense ) {
		if ( !( $oLicense instanceof ICWP_EDD_LicenseVO ) ) {
			throw new Exception( sprintf( 'Attempt to store something that is not even a license: %s', gettype( $oLicense ) ) );
		}

		$this->setOpt( 'license_official_status', $oLicense->getLicenseStatus() );

		if ( !$oLicense->isSuccess() || $oLicense->getLicenseStatus() != 'valid' ) {
			throw new Exception( 'Attempt to store invalid license.' );
		}

		$bLicenseWasValid = $this->hasValidWorkingLicense();

		$this->setOpt( 'license_key', '' )
			 ->setOpt( 'is_shield_central', $oLicense->isShieldCentral() )
			 ->setOptAt( 'license_expires_at', $oLicense->getExpiresAt() )
			 ->setOfficialLicenseRegisteredEmail( $oLicense->getCustomerEmail() );

		$bCurrentLicenseValid = $this->isOfficialLicenseStatusValid() && !$this->isLicenseExpired();

		if ( !$this->isLicenseActive() || ( !$bLicenseWasValid && $bCurrentLicenseValid ) ) {
			$this->setOpt( 'license_activated_at', $this->loadDP()->time() );
		}
		return $this->setLastErrors();
	}

	/**
	 * @param array $aAjaxResponse
	 * @return array
	 */
	public function handleAuthAjax( $aAjaxResponse ) {

		if ( empty( $aAjaxResponse ) ) {
			switch ( $this->loadDP()->request( 'exec' ) ) {

				case 'license_handling':
					$aAjaxResponse = $this->ajaxExec_LicenseHandling();
					break;

				case 'connection_debug':
					$aAjaxResponse = $this->ajaxExec_ConnectionDebug();
					break;

				default:
					break;
			}
		}
		return parent::handleAuthAjax( $aAjaxResponse );
	}

	/**
	 * @return array
	 */
	protected function ajaxExec_LicenseHandling() {
		$bSuccess = false;

		$sLicenseAction = $this->loadDP()->post( 'license-action' );

		if ( $sLicenseAction == 'check' ) {
			$bSuccess = $this->verifyLicense( true )
							 ->hasValidWorkingLicense();
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
			$this->setOpt( 'license_official_status', 'n/a' );
		}

		return array( 'success' => $bSuccess );
	}

	/**
	 * @return array
	 */
	protected function ajaxExec_ConnectionDebug() {
		$bSuccess = false;

		$sStoreUrl = add_query_arg(
			array( 'license_ping' => 'Y' ),
			$this->getLicenseStoreUrl()
		);

		$mResponse = $this->loadFS()->requestUrl(
			$sStoreUrl,
			array(
				'method' => 'POST',
				'body'   => array( 'ping' => 'pong' )
			),
			true
		);

		if ( is_wp_error( $mResponse ) ) {
			$sResult = $mResponse->get_error_message();
		}
		else if ( is_array( $mResponse ) && !empty( $mResponse[ 'body' ] ) ) {
			$aResult = @json_decode( $mResponse[ 'body' ], true );
			if ( isset( $aResult[ 'success' ] ) && $aResult[ 'success' ] ) {
				$bSuccess = true;
				$sResult = 'Successful - no problems detected communicating with license server.';
			}
			else {
				$sResult = 'Unknown failure due to unexpected response';
			}
		}
		else {
			$sResult = 'Unknown error as we could not get a response back from the server';
		}

		return array(
			'success' => $bSuccess,
			'message' => esc_html( esc_js( $sResult ) )
		);
	}

	/**
	 * @param string $sDeactivatedReason
	 */
	private function deactivate( $sDeactivatedReason = '' ) {

		$this->setOpt( 'license_expires_at', 0 )
			 ->setOpt( 'is_shield_central', false )
			 ->setOfficialLicenseRegisteredEmail( 'n/a' );

		if ( $this->isOfficialLicenseStatusValid() ) {
			$this->setOpt( 'license_official_status', 'cleared' );
		}
		if ( $this->isLicenseActive() ) {
			$this->setOptAt( 'license_deactivated_at' );
		}
		if ( !empty( $sDeactivatedReason ) ) {
			$this->setOpt( 'license_deactivated_reason', $sDeactivatedReason );
		}
		// force all options to resave i.e. reset premium to defaults.
		add_filter( $this->prefix( 'force_options_resave' ), '__return_true' );
	}

	/**
	 * License check normally only happens when the verification_at expires (~3 days) for a currently valid license.
	 * @param bool $bForceCheck
	 * @return $this
	 */
	public function verifyLicense( $bForceCheck = true ) {
		$nNow = $this->loadDP()->time();

		// If your last license verification has expired and it's been 4hrs since your last check.
		$bCheck = $bForceCheck || ( $this->hasValidWorkingLicense() && $this->isLastVerifiedExpired()
									&& ( $nNow - $this->getLicenseLastCheckedAt() > HOUR_IN_SECONDS*4 ) );

		if ( $bCheck ) {
			$this->setLicenseLastCheckedAt();

			$oLicense = $this->retrieveLicense();
			try {
				$this->storeLicense( $oLicense )
					 ->setLicenseVerifiedAt( $nNow );
				$bSuccess = true;
			}
			catch ( Exception $oE ) {
				$this->setLastErrors( 'Could not find a valid license' );
				$bSuccess = false;
			}

			if ( !$bSuccess && ( $bForceCheck || $this->isLastVerifiedGraceExpired() ) ) {
				$this->deactivate();
			}
		}

		return $this;
	}

	/**
	 * @return ICWP_EDD_LicenseVO|null
	 */
	private function retrieveLicense() {

		$sPass = wp_generate_password( 16 );

		$this->setKeylessRequestAt()
			 ->setKeylessRequestHash( sha1( $sPass.$this->loadWp()->getHomeUrl() ) )
			 ->savePluginOptions();

		$oLicense = $this->loadEdd()
						 ->setRequestParams( array( 'nonce' => $sPass ) )
						 ->activateLicenseKeyless( $this->getLicenseStoreUrl(), $this->getLicenseItemId() );

		// clear the handshake data
		$this->setKeylessRequestAt( 0 )
			 ->setKeylessRequestHash( '' )
			 ->savePluginOptions();

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
	 * @return int
	 */
	protected function getLicenseVerifiedAt() {
		return $this->getOpt( 'license_verified_at' );
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
		return $this->getOpt( 'is_shield_central', false );
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
	 * IMPORTANT: Method used by Shield Central. Modify with care.
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
	protected function isLastVerifiedExpired() {
		return ( $this->loadDP()->time() - $this->getLicenseVerifiedAt()
				 > $this->getDef( 'lic_verify_expire_days' )*DAY_IN_SECONDS );
	}

	/**
	 * @return bool
	 */
	protected function isLastVerifiedGraceExpired() {
		$nGracePeriod = ( $this->getDef( 'lic_verify_expire_days' ) + $this->getDef( 'lic_verify_expire_grace_days' ) )
						*DAY_IN_SECONDS;
		return $this->loadDP()->time() - $this->getLicenseVerifiedAt() > $nGracePeriod;
	}

	/**
	 * @param string $sEmail
	 * @return string
	 */
	protected function setOfficialLicenseRegisteredEmail( $sEmail ) {
		return $this->setOpt( 'license_registered_email', $sEmail );
	}

	/**
	 * @param int $nAt
	 * @return $this
	 */
	protected function setLicenseLastCheckedAt( $nAt = null ) {
		return $this->setOptAt( 'license_last_checked_at', $nAt );
	}

	/**
	 * @param int $nAt
	 * @return $this
	 */
	protected function setLicenseVerifiedAt( $nAt = null ) {
		return $this->setOptAt( 'license_verified_at', $nAt );
	}

	/**
	 * @param string $sKey
	 * @return string|null
	 */
	public function verifyLicenseKeyFormat( $sKey ) {
		$sCleanKey = null;

		$sKey = $this->cleanLicenseKey( $sKey );
		$bValid = !empty( $sKey ) && is_string( $sKey )
				  && ( strlen( $sKey ) == $this->getDef( 'license_key_length' ) );

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
		$this->verifyLicense( false );
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