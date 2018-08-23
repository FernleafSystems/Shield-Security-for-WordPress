<?php

if ( class_exists( 'ICWP_WPSF_FeatureHandler_License', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base_wpsf.php' );

class ICWP_WPSF_FeatureHandler_License extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	protected function doPostConstruction() {
		$this->verifyLicense( false );
		add_filter( $this->getPremiumLicenseFilterName(), array( $this, 'hasValidWorkingLicense' ), PHP_INT_MAX );
	}

	/**
	 * Override this to customize anything with the display of the page
	 * @param array $aData
	 */
	protected function displayModulePage( $aData = array() ) {
		$oWp = $this->loadWp();
		$oCurrent = $this->loadLicense();

		$nExpiresAt = $oCurrent->getExpiresAt();
		if ( $nExpiresAt > 0 && $nExpiresAt != PHP_INT_MAX ) {
			$sExpiresAt = $oWp->getTimeStampForDisplay( $nExpiresAt );
		}
		else {
			$sExpiresAt = 'n/a';
		}

		$aLicenseTableVars = array(
			'product_name'    => $this->getLicenseItemName(),
			'license_active'  => $this->hasValidWorkingLicense() ? 'Active' : 'Not Active',
			'license_expires' => $sExpiresAt,
			'license_email'   => $oCurrent->getCustomerEmail(),
			'last_checked'    => $oWp->getTimeStampForDisplay( $oCurrent->getLastRequestAt() ),
			'last_errors'     => $this->hasLastErrors() ? $this->getLastErrors() : ''
		);
		if ( !$this->isKeyless() ) {
			$aLicenseTableVars[ 'license_key' ] = $this->hasLicenseKey() ? $this->getLicenseKey() : 'n/a';
		}

		$aData = array(
			'vars'    => array(
				'license_table'  => $aLicenseTableVars,
				'activation_url' => $oWp->getHomeUrl()
			),
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
				'shield_pro_url'           => 'https://icwp.io/shieldpro',
				'shield_pro_more_info_url' => 'https://icwp.io/shld1',
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
			'alt' => $this->renderTemplate( 'snippets/pro.php', $aData )
		);
		parent::displayModulePage( $aData );
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
	 * @return ICWP_EDD_LicenseVO
	 */
	protected function loadLicense() {
		return $this->loadEdd()->getLicenseVoFromData( $this->getLicenseData() );
	}

	/**
	 * @return array
	 */
	protected function getLicenseData() {
		$aData = $this->getOpt( 'license_data', array() );
		return is_array( $aData ) ? $aData : array();
	}

	/**
	 * @return $this
	 */
	protected function clearLicenseData() {
		return $this->setOpt( 'license_data', array() );
	}

	/**
	 * @param ICWP_EDD_LicenseVO $oLic
	 * @return $this
	 */
	protected function setLicenseData( $oLic ) {
		return $this->setOpt( 'license_data', $this->loadDP()->convertStdClassToArray( $oLic->getRaw() ) );
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
	 * License check normally only happens when the verification_at expires (~3 days)
	 * for a currently valid license.
	 * @param bool $bForceCheck
	 * @return $this
	 */
	public function verifyLicense( $bForceCheck = true ) {
		$nNow = $this->loadDP()->time();
		$oCurrent = $this->loadLicense();

		// If your last license verification has expired and it's been 4hrs since your last check.
		$bCheck = $bForceCheck || ( $this->isLicenseActive() && !$oCurrent->isReady() )
				  || ( $this->hasValidWorkingLicense() && $this->isLastVerifiedExpired()
					   && ( $nNow - $this->getLicenseLastCheckedAt() > HOUR_IN_SECONDS*4 )
				  );

		// 1 check in 20 seconds
		if ( $bCheck && ( $nNow - $this->getLicenseLastCheckedAt() > 20 ) ) {

			$this->setLicenseLastCheckedAt()
				 ->savePluginOptions();

			/** @var ICWP_WPSF_Processor_License $oPro */
			$oPro = $this->getProcessor();

			$oLookupLicense = $this->lookupOfficialLicense();
			if ( $oLookupLicense->isValid() ) {
				$oCurrent = $oLookupLicense;
				$oLookupLicense->updateLastVerifiedAt();
				$this->activateLicense();
				$oPro->addToAuditEntry( 'Pro License check succeeded.', 1, 'license_check_success' );
			}
			else {
				$oCurrent->setLastRequestAt( $nNow );
				if ( $oCurrent->isValid() ) { // we have something valid previously stored

					if ( !$bForceCheck && $this->isWithinVerifiedGraceExpired() ) {
						$this->sendLicenseWarningEmail();
						$oPro->addToAuditEntry( 'License check failed. Sending Warning Email.', 2, 'license_check_failed' );
					}
					else if ( $bForceCheck || $this->isLastVerifiedGraceExpired() ) {
						$oCurrent = $oLookupLicense;
						$this->deactivate( sprintf( _wpsf__( 'Automatic license verification failed after %s days.' ), 6 ) );
						$this->sendLicenseDeactivatedEmail();
						$oPro->addToAuditEntry( 'License check failed. Deactivating Pro.', 3, 'license_check_failed' );
					}
				}
			}

			$this->setLicenseData( $oCurrent )
				 ->savePluginOptions();

			try {
			}
			catch ( Exception $oE ) {
//				$oCurrent->setLastErrors( 'Could not find a valid license' );
			}
		}

		return $this;
	}

	/**
	 * @return $this
	 */
	protected function activateLicense() {
		if ( !$this->isLicenseActive() ) {
			$this->setOpt( 'license_activated_at', $this->loadLicense()->getLastRequestAt() );
		}
		return $this;
	}

	/**
	 */
	protected function sendLicenseWarningEmail() {
		$nNow = $this->loadDP()->time();
		$bCanSend = $nNow - $this->getOpt( 'last_warning_email_sent_at' ) > DAY_IN_SECONDS;

		if ( $bCanSend ) {
			$aMessage = array(
				_wpsf__( 'Attempts to verify Shield Pro license has just failed.' ),
				sprintf( _wpsf__( 'Please check your license on-site: %s' ), $this->getUrl_AdminPage() ),
				sprintf( _wpsf__( 'If this problem persists, please contact support: %s' ), 'https://support.onedollarplugin.com/' )
			);
			$this->getEmailProcessor()
				 ->sendEmailWithWrap(
					 $this->getPluginDefaultRecipientAddress(),
					 'Pro License Check Has Failed',
					 $aMessage
				 );
		}
	}

	/**
	 */
	private function sendLicenseDeactivatedEmail() {
		$aMessage = array(
			_wpsf__( 'All attempts to verify Shield Pro license have failed.' ),
			sprintf( _wpsf__( 'Please check your license on-site: %s' ), $this->getUrl_AdminPage() ),
			sprintf( _wpsf__( 'If this problem persists, please contact support: %s' ), 'https://support.onedollarplugin.com/' )
		);
		$this->getEmailProcessor()
			 ->sendEmailWithWrap(
				 $this->getPluginDefaultRecipientAddress(),
				 '[Action May Be Required] Pro License Has Been Deactivated',
				 $aMessage
			 );
	}

	/**
	 * @return ICWP_EDD_LicenseVO|null
	 */
	private function lookupOfficialLicense() {

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
	 * Unused
	 * @return string
	 */
	public function getLicenseItemIdShieldCentral() {
		return $this->getDef( 'license_item_id_sc' );
	}

	/**
	 * @return string
	 */
	public function getLicenseItemName() {
		return $this->loadLicense()->isCentral() ?
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
	protected function getLicenseLastCheckedAt() {
		return $this->getOpt( 'license_last_checked_at' );
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
	public function isLicenseKeyValidFormat() {
		return !is_null( $this->verifyLicenseKeyFormat( $this->getLicenseKey() ) );
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
		$oLic = $this->loadLicense();
		return ( $this->isKeyless() || $this->isLicenseKeyValidFormat() )
			   && $oLic->isValid() && $this->isLicenseActive();
	}

	/**
	 * @return bool
	 */
	protected function isKeyless() {
		return (bool)$this->getDef( 'keyless' );
	}

	/**
	 * Expires in 3 days.
	 * @return bool
	 */
	protected function isLastVerifiedExpired() {
		return ( $this->loadDP()->time() - $this->loadLicense()->getLastVerifiedAt() )
			   > $this->getDef( 'lic_verify_expire_days' )*DAY_IN_SECONDS;
	}

	/**
	 * @return bool
	 */
	protected function isLastVerifiedGraceExpired() {
		$nGracePeriod = ( $this->getDef( 'lic_verify_expire_days' ) + $this->getDef( 'lic_verify_expire_grace_days' ) )
						*DAY_IN_SECONDS;
		return ( $this->loadDP()->time() - $this->loadLicense()->getLastVerifiedAt() ) > $nGracePeriod;
	}

	/**
	 * @return bool
	 */
	protected function isWithinVerifiedGraceExpired() {
		return false;//$this->isLastVerifiedExpired() && !$this->isLastVerifiedGraceExpired();
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
	protected function setLicenseLastRequestedAt( $nAt = null ) {
		return $this->setOptAt( 'license_last_request_at', $nAt );
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
		return parent::getIfShowModuleMenuItem() && self::getConn()->isPremiumExtensionsEnabled()
			   && $this->getConn()->getHasPermissionToManage();
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
	 * @return bool
	 */
	protected function isEnabledForUiSummary() {
		return $this->hasValidWorkingLicense();
	}

	/**
	 * @param array $aOptionsParams
	 * @return array
	 * @throws Exception
	 */
	protected function loadStrings_SectionTitles( $aOptionsParams ) {

		$sName = $this->getConn()->getHumanName();
		switch ( $aOptionsParams[ 'slug' ] ) {

			case 'section_license_options' :
				$sTitle = _wpsf__( 'License Options' );
				$sTitleShort = _wpsf__( 'License Options' );
				$aSummary = array(
					sprintf( _wpsf__( 'Purpose - %s' ), sprintf( _wpsf__( 'Activate %s Pro Extensions.' ), $sName ) ),
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