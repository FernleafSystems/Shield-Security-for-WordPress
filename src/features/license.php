<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_FeatureHandler_License extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	protected function redirectToInsightsSubPage() {
		Services::Response()->redirect(
			$this->getCon()->getModule( 'insights' )->getUrl_AdminPage(),
			[ 'inav' => 'license' ]
		);
	}

	protected function setupCustomHooks() {
		parent::setupCustomHooks();
		add_filter( $this->getCon()->getPremiumLicenseFilterName(), [ $this, 'hasValidWorkingLicense' ], PHP_INT_MAX );
	}

	/**
	 * @return boolean
	 */
	public function getIfShowModuleMenuItem() {
		return parent::getIfShowModuleMenuItem() && !$this->isPremium();
	}

	public function action_doFeatureShutdown() {
		$this->verifyLicense( false );
		parent::action_doFeatureShutdown();
	}

	/**
	 * @return array
	 */
	protected function getDisplayStrings() {
		return Services::DataManipulation()->mergeArraysRecursive(
			parent::getDisplayStrings(),
			[
				'product_name'    => __( 'Name', 'wp-simple-firewall' ),
				'license_active'  => __( 'Active', 'wp-simple-firewall' ),
				'license_status'  => __( 'Status', 'wp-simple-firewall' ),
				'license_key'     => __( 'Key', 'wp-simple-firewall' ),
				'license_expires' => __( 'Expires', 'wp-simple-firewall' ),
				'license_email'   => __( 'Owner', 'wp-simple-firewall' ),
				'last_checked'    => __( 'Checked', 'wp-simple-firewall' ),
				'last_errors'     => __( 'Error', 'wp-simple-firewall' ),
			]
		);
	}

	/**
	 * @return Shield\License\EddLicenseVO
	 */
	protected function loadLicense() {
		return ( new Shield\License\EddLicenseVO() )->applyFromArray( $this->getLicenseData() );
	}

	/**
	 * @return array
	 */
	protected function getLicenseData() {
		$aData = $this->getOpt( 'license_data', [] );
		return is_array( $aData ) ? $aData : [];
	}

	/**
	 * @return $this
	 */
	protected function clearLicenseData() {
		return $this->setOpt( 'license_data', [] );
	}

	/**
	 * @param Shield\License\EddLicenseVO $oLic
	 * @return $this
	 */
	protected function setLicenseData( $oLic ) {
		return $this->setOpt( 'license_data', $oLic->getRawDataAsArray() );
	}

	/**
	 * @param array $aAjaxResponse
	 * @return array
	 */
	public function handleAuthAjax( $aAjaxResponse ) {

		if ( empty( $aAjaxResponse ) ) {
			switch ( Services::Request()->request( 'exec' ) ) {

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
	private function ajaxExec_LicenseHandling() {
		$bSuccess = false;
		$sMessage = 'Unsupported license action';

		$sLicenseAction = Services::Request()->post( 'license-action' );

		$nCheckInterval = $this->getLicenseNotCheckedForInterval();
		if ( $nCheckInterval < 20 ) {
			$nWait = 20 - $nCheckInterval;
			$sMessage = sprintf(
				__( 'Please wait %s before attempting another license check.', 'wp-simple-firewall' ),
				sprintf( _n( '%s second', '%s seconds', $nWait, 'wp-simple-firewall' ), $nWait )
			);
		}
		else if ( $sLicenseAction == 'check' ) {
			$bSuccess = $this->verifyLicense( true )
							 ->hasValidWorkingLicense();
			$sMessage = $bSuccess ? __( 'Valid license found.', 'wp-simple-firewall' ) : __( "Valid license couldn't be found.", 'wp-simple-firewall' );
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

		return [
			'success' => $bSuccess,
			'message' => $sMessage,
		];
	}

	/**
	 * @return array
	 */
	private function ajaxExec_ConnectionDebug() {
		$bSuccess = false;

		$oHttpReq = Services::HttpRequest()
							->request(
								add_query_arg( [ 'license_ping' => 'Y' ], $this->getLicenseStoreUrl() ),
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
		$oCon = $this->getCon();
		// Is a check actually required and permitted
		$bCheckReq = $this->isLicenseCheckRequired() && $this->canLicenseCheck();

		// 1 check in 20 seconds
		if ( ( $bForceCheck || $bCheckReq ) && $this->getIsLicenseNotCheckedFor( 20 ) ) {

			$oCurrent = $this->loadLicense();

			$this->touchLicenseCheckFileFlag()
				 ->setLicenseLastCheckedAt()
				 ->savePluginOptions();

			/** @var ICWP_WPSF_Processor_License $oPro */
			$oPro = $this->getProcessor();

			$oLookupLicense = $this->lookupOfficialLicense();
			if ( $oLookupLicense->isValid() ) {
				$oCurrent = $oLookupLicense;
				$oLookupLicense->updateLastVerifiedAt( true );
				$this->activateLicense()
					 ->clearLastErrors();
				$oCon->fireEvent( 'check_success' );
			}
			else {
				if ( $oCurrent->isValid() ) { // we have something valid previously stored

					if ( !$bForceCheck && $this->isWithinVerifiedGraceExpired() ) {
						$this->sendLicenseWarningEmail();
						$oCon->fireEvent( 'check_fail_email' );
					}
					else if ( $bForceCheck || $oCurrent->isExpired() || $this->isLastVerifiedGraceExpired() ) {
						$oCurrent = $oLookupLicense;
						$this->deactivate( __( 'Automatic license verification failed.', 'wp-simple-firewall' ) );
						$this->sendLicenseDeactivatedEmail();
						$oCon->fireEvent( 'check_fail_deactivate' );
					}
				}
				else {
					// No previously valid license, and the license lookup also failed but the http request was successful.
					if ( $oLookupLicense->isReady() ) {
						$this->deactivate();
						$oCurrent = $oLookupLicense;
					}
				}
			}

			$oCurrent->setLastRequestAt( Services::Request()->ts() );
			$this->setLicenseData( $oCurrent )
				 ->savePluginOptions();
		}

		return $this;
	}

	/**
	 * @return bool
	 */
	private function isLicenseCheckRequired() {
		return ( $this->isLicenseMaybeExpiring() && $this->getIsLicenseNotCheckedFor( HOUR_IN_SECONDS*4 ) )
			   || ( $this->isLicenseActive()
					&& !$this->loadLicense()->isReady() && $this->getIsLicenseNotCheckedFor( HOUR_IN_SECONDS ) )
			   || ( $this->hasValidWorkingLicense() && $this->isLastVerifiedExpired()
					&& $this->getIsLicenseNotCheckedFor( HOUR_IN_SECONDS*4 ) );
	}

	/**
	 * @return bool
	 */
	private function canLicenseCheck() {
		return !in_array( Services::Request()->query( 'shield_action' ), [ 'keyless_handshake', 'license_check' ] )
			   && $this->canLicenseCheck_FileFlag();
	}

	/**
	 * @return bool
	 */
	private function canLicenseCheck_FileFlag() {
		$oFs = Services::WpFs();
		$sFileFlag = $this->getCon()->getPath_Flags( 'license_check' );
		$nMtime = $oFs->exists( $sFileFlag ) ? $oFs->getModifiedTime( $sFileFlag ) : 0;
		return ( Services::Request()->ts() - $nMtime ) > MINUTE_IN_SECONDS;
	}

	/**
	 * @return $this
	 */
	private function touchLicenseCheckFileFlag() {
		Services::WpFs()->touch( $this->getCon()->getPath_Flags( 'license_check' ) );
		return $this;
	}

	/**
	 * @return bool
	 */
	protected function isLicenseMaybeExpiring() {
		$bNearly = $this->isLicenseActive() &&
				   (
					   abs( Services::Request()->ts() - $this->loadLicense()->getExpiresAt() )
					   < ( DAY_IN_SECONDS/2 )
				   );
		return $bNearly;
	}

	/**
	 * @return $this
	 */
	protected function activateLicense() {
		if ( !$this->isLicenseActive() ) {
			$nAt = $this->loadLicense()->getLastRequestAt();
			$this->setOptAt( 'license_activated_at', $nAt > 0 ? $nAt : null );
		}
		return $this;
	}

	/**
	 */
	protected function sendLicenseWarningEmail() {
		$bCanSend = Services::Request()->ts() - $this->getOpt( 'last_warning_email_sent_at' ) > DAY_IN_SECONDS;

		if ( $bCanSend ) {
			$this->setOptAt( 'last_warning_email_sent_at' )->savePluginOptions();
			$aMessage = [
				__( 'Attempts to verify Shield Pro license has just failed.', 'wp-simple-firewall' ),
				sprintf( __( 'Please check your license on-site: %s', 'wp-simple-firewall' ), $this->getUrl_AdminPage() ),
				sprintf( __( 'If this problem persists, please contact support: %s', 'wp-simple-firewall' ), 'https://support.onedollarplugin.com/' )
			];
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
		$nNow = Services::Request()->ts();

		if ( ( $nNow - $this->getOpt( 'last_deactivated_email_sent_at' ) ) > DAY_IN_SECONDS ) {
			$this->setOptAt( 'last_deactivated_email_sent_at' )->savePluginOptions();
			$aMessage = [
				__( 'All attempts to verify Shield Pro license have failed.', 'wp-simple-firewall' ),
				sprintf( __( 'Please check your license on-site: %s', 'wp-simple-firewall' ), $this->getUrl_AdminPage() ),
				sprintf( __( 'If this problem persists, please contact support: %s', 'wp-simple-firewall' ), 'https://support.onedollarplugin.com/' )
			];
			$this->getEmailProcessor()
				 ->sendEmailWithWrap(
					 $this->getPluginDefaultRecipientAddress(),
					 '[Action May Be Required] Pro License Has Been Deactivated',
					 $aMessage
				 );
		}
	}

	/**
	 * @return Shield\License\EddLicenseVO
	 */
	private function lookupOfficialLicense() {

		$sPass = wp_generate_password( 16 );

		$this->setKeylessRequestAt()
			 ->setKeylessRequestHash( sha1( $sPass.Services::WpGeneral()->getHomeUrl() ) )
			 ->savePluginOptions();

		$oLicense = $this->loadEdd()
						 ->setRequestParams( [ 'nonce' => $sPass ] )
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
	 * @param int $nTimePeriod
	 * @return bool
	 */
	private function getIsLicenseNotCheckedFor( $nTimePeriod ) {
		return ( $this->getLicenseNotCheckedForInterval() > $nTimePeriod );
	}

	/**
	 * @return int
	 */
	private function getLicenseNotCheckedForInterval() {
		return ( Services::Request()->ts() - $this->getLicenseLastCheckedAt() );
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
		return ( Services::Request()->ts() - $this->loadLicense()->getLastVerifiedAt() )
			   > $this->getDef( 'lic_verify_expire_days' )*DAY_IN_SECONDS;
	}

	/**
	 * @return bool
	 */
	protected function isLastVerifiedGraceExpired() {
		$nGracePeriod = ( $this->getDef( 'lic_verify_expire_days' ) + $this->getDef( 'lic_verify_expire_grace_days' ) )
						*DAY_IN_SECONDS;
		return ( Services::Request()->ts() - $this->loadLicense()->getLastVerifiedAt() ) > $nGracePeriod;
	}

	/**
	 * @return bool
	 */
	protected function isWithinVerifiedGraceExpired() {
		return $this->isLastVerifiedExpired() && !$this->isLastVerifiedGraceExpired();
	}

	/**
	 * @param int $nAt
	 * @return $this
	 */
	protected function setLicenseLastCheckedAt( $nAt = null ) {
		return $this->setOptAt( 'license_last_checked_at', $nAt );
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
		return ( Services::Request()->ts() - $this->getKeylessRequestAt() )
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
		$nTime = is_numeric( $nTime ) ? $nTime : Services::Request()->ts();
		return $this->setOpt( 'keyless_request_at', $nTime );
	}

	/**
	 * @return bool
	 */
	protected function isEnabledForUiSummary() {
		return $this->hasValidWorkingLicense();
	}

	public function buildInsightsVars() {
		$oWp = Services::WpGeneral();
		$oCarbon = new \Carbon\Carbon();

		$oCurrent = $this->loadLicense();

		$nExpiresAt = $oCurrent->getExpiresAt();
		if ( $nExpiresAt > 0 && $nExpiresAt != PHP_INT_MAX ) {
			$sExpiresAt = $oCarbon->setTimestamp( $nExpiresAt )->diffForHumans()
						  .sprintf( '<br/><small>%s</small>', $oWp->getTimeStampForDisplay( $nExpiresAt ) );
		}
		else {
			$sExpiresAt = 'n/a';
		}

		$nLastReqAt = $oCurrent->getLastRequestAt();
		if ( empty( $nLastReqAt ) ) {
			$sChecked = __( 'Never', 'wp-simple-firewall' );
		}
		else {
			$sChecked = $oCarbon->setTimestamp( $nLastReqAt )->diffForHumans()
						.sprintf( '<br/><small>%s</small>', $oWp->getTimeStampForDisplay( $nLastReqAt ) );
		}
		$aLicenseTableVars = [
			'product_name'    => $this->getLicenseItemName(),
			'license_active'  => $this->hasValidWorkingLicense() ? __( 'Yes', 'wp-simple-firewall' ) : __( 'Not Active', 'wp-simple-firewall' ),
			'license_expires' => $sExpiresAt,
			'license_email'   => $oCurrent->getCustomerEmail(),
			'last_checked'    => $sChecked,
			'last_errors'     => $this->hasLastErrors() ? $this->getLastErrors() : ''
		];
		if ( !$this->isKeyless() ) {
			$aLicenseTableVars[ 'license_key' ] = $this->hasLicenseKey() ? $this->getLicenseKey() : 'n/a';
		}
		$aData = [
			'vars'    => [
				'license_table'  => $aLicenseTableVars,
				'activation_url' => $oWp->getHomeUrl()
			],
			'inputs'  => [
				'license_key' => [
					'name'      => $this->prefixOptionKey( 'license_key' ),
					'maxlength' => $this->getDef( 'license_key_length' ),
				]
			],
			'ajax'    => [
				'license_handling' => $this->getAjaxActionData( 'license_handling' ),
				'connection_debug' => $this->getAjaxActionData( 'connection_debug' )
			],
			'aHrefs'  => [
				'shield_pro_url'           => 'https://icwp.io/shieldpro',
				'shield_pro_more_info_url' => 'https://icwp.io/shld1',
				'iframe_url'               => $this->getDef( 'landing_page_url' ),
				'keyless_cp'               => $this->getDef( 'keyless_cp' ),
			],
			'flags'   => [
				'show_key'              => !$this->isKeyless(),
				'has_license_key'       => $this->isLicenseKeyValidFormat(),
				'show_ads'              => false,
				'button_enabled_check'  => true,
				'button_enabled_remove' => $this->isLicenseKeyValidFormat(),
				'show_standard_options' => false,
				'show_alt_content'      => true,
			],
			'strings' => $this->getDisplayStrings(),
		];
		return $aData;
	}

	/**
	 * @return Shield\Modules\License\Strings
	 */
	protected function loadStrings() {
		return new Shield\Modules\License\Strings();
	}
}