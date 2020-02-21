<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities;

class ICWP_WPSF_FeatureHandler_License extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	/**
	 * @var Shield\Modules\License\Lib\LicenseHandler
	 */
	private $oLicHandler;

	/**
	 * @return Shield\Modules\License\Lib\LicenseHandler
	 */
	public function getLicenseHandler() {
		if ( !isset( $this->oLicHandler ) ) {
			$this->oLicHandler = ( new Shield\Modules\License\Lib\LicenseHandler() )->setMod( $this );
		}
		return $this->oLicHandler;
	}

	protected function redirectToInsightsSubPage() {
		Services::Response()->redirect(
			$this->getCon()->getModule_Insights()->getUrl_AdminPage(),
			[ 'inav' => 'license' ]
		);
	}

	/**
	 * @return bool
	 */
	public function getIfShowModuleMenuItem() {
		return parent::getIfShowModuleMenuItem() && !$this->isPremium();
	}

	public function onPluginShutdown() {
		$this->verifyLicense( false );
		parent::onPluginShutdown();
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
	public function clearLicenseData() {
		return $this->setOpt( 'license_data', [] );
	}

	/**
	 * @param Utilities\Licenses\EddLicenseVO $oLic
	 * @return $this
	 */
	protected function setLicenseData( $oLic ) {
		return $this->setOpt( 'license_data', $oLic->getRawDataAsArray() );
	}

	/**
	 * @param string $sDeactivatedReason
	 */
	public function deactivate( $sDeactivatedReason = '' ) {
		$oOpts = $this->getOptions();
		if ( $this->isLicenseActive() ) {
			$oOpts->setOptAt( 'license_deactivated_at' );
		}

		if ( !empty( $sDeactivatedReason ) ) {
			$oOpts->setOpt( 'license_deactivated_reason', $sDeactivatedReason );
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
				 ->setLicenseLastCheckedAt();
			$this->saveModOptions();

			$oLookupLicense = $this->getLicenseHandler()->lookup();
			if ( $oLookupLicense->isValid() ) {
				$oCurrent = $oLookupLicense;
				$oCurrent->updateLastVerifiedAt( true );
				$this->activateLicense()
					 ->clearLastErrors();
				$oCon->fireEvent( 'lic_check_success' );
			}
			else {
				if ( $oCurrent->isValid() ) { // we have something valid previously stored

					if ( !$bForceCheck && $this->isWithinVerifiedGraceExpired() ) {
						$this->sendLicenseWarningEmail();
						$oCon->fireEvent( 'lic_fail_email' );
					}
					elseif ( $bForceCheck || $oCurrent->isExpired() || $this->isLastVerifiedGraceExpired() ) {
						$oCurrent = $oLookupLicense;
						$this->deactivate( __( 'Automatic license verification failed.', 'wp-simple-firewall' ) );
						$this->sendLicenseDeactivatedEmail();
						$oCon->fireEvent( 'lic_fail_deactivate' );
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

			$oCurrent->last_request_at = Services::Request()->ts();
			$this->setLicenseData( $oCurrent );
			$this->saveModOptions();
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
		return !in_array( $this->getCon()->getShieldAction(), [ 'keyless_handshake', 'license_check' ] )
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
		return $this->isLicenseActive() &&
			   (
				   abs( Services::Request()->ts() - $this->loadLicense()->getExpiresAt() )
				   < ( DAY_IN_SECONDS/2 )
			   );
	}

	/**
	 * @return $this
	 */
	protected function activateLicense() {
		if ( !$this->isLicenseActive() ) {
			$this->getOptions()->setOptAt( 'license_activated_at' );
		}
		return $this;
	}

	/**
	 */
	protected function sendLicenseWarningEmail() {
		$oOpts = $this->getOptions();

		$bCanSend = Services::Request()
							->carbon()
							->subDay( 1 )->timestamp > $oOpts->getOpt( 'last_warning_email_sent_at' );

		if ( $bCanSend ) {
			$oOpts->setOptAt( 'last_warning_email_sent_at' );
			$this->saveModOptions();

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
		$oOpts = $this->getOptions();

		$bCanSend = Services::Request()
							->carbon()
							->subDay( 1 )->timestamp > $oOpts->getOpt( 'last_deactivated_email_sent_at' );

		if ( $bCanSend ) {
			$oOpts->setOptAt( 'last_deactivated_email_sent_at' );
			$this->saveModOptions();

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
	public function getLicenseItemName() {
		return $this->loadLicense()->is_central ?
			$this->getDef( 'license_item_name_sc' ) :
			$this->getDef( 'license_item_name' );
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
	public function getLicenseNotCheckedForInterval() {
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
		return $oLic->isValid() && $this->isLicenseActive();
	}

	/**
	 * Expires in 3 days.
	 * @return bool
	 */
	protected function isLastVerifiedExpired() {
		return ( Services::Request()->ts() - $this->loadLicense()->last_verified_at )
			   > $this->getDef( 'lic_verify_expire_days' )*DAY_IN_SECONDS;
	}

	/**
	 * @return bool
	 */
	protected function isLastVerifiedGraceExpired() {
		$nGracePeriod = ( $this->getDef( 'lic_verify_expire_days' ) + $this->getDef( 'lic_verify_expire_grace_days' ) )
						*DAY_IN_SECONDS;
		return ( Services::Request()->ts() - $this->loadLicense()->last_verified_at ) > $nGracePeriod;
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
		$this->getOptions()->setOptAt( 'license_last_checked_at', $nAt );
		return $this;
	}

	/**
	 * @param string $sNonce - empty string to clear the nonce
	 * @return $this
	 */
	public function setKeylessHandshakeNonce( $sNonce = '' ) {
		$this->getOptions()
			 ->setOpt( 'keyless_handshake_hash', $sNonce )
			 ->setOpt( 'keyless_handshake_until',
				 empty( $sNonce ) ? 0 : Services::Request()->ts() + $this->getDef( 'keyless_handshake_expire' )
			 );
		return $this->saveModOptions();
	}

	/**
	 * @return bool
	 */
	protected function isEnabledForUiSummary() {
		return $this->hasValidWorkingLicense();
	}

	public function buildInsightsVars() {
		$oWp = Services::WpGeneral();
		$oCon = $this->getCon();
		$oCarbon = Services::Request()->carbon();

		$oCurrent = $this->loadLicense();

		$nExpiresAt = $oCurrent->getExpiresAt();
		if ( $nExpiresAt > 0 && $nExpiresAt != PHP_INT_MAX ) {
			$sExpiresAt = $oCarbon->setTimestamp( $nExpiresAt )->diffForHumans()
						  .sprintf( '<br/><small>%s</small>', $oWp->getTimeStampForDisplay( $nExpiresAt ) );
		}
		else {
			$sExpiresAt = 'n/a';
		}

		$nLastReqAt = $oCurrent->last_request_at;
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
			'license_email'   => $oCurrent->customer_email,
			'last_checked'    => $sChecked,
			'last_errors'     => $this->hasLastErrors() ? $this->getLastErrors() : ''
		];
		return [
			'vars'    => [
				'license_table'  => $aLicenseTableVars,
				'activation_url' => $oWp->getHomeUrl()
			],
			'inputs'  => [
				'license_key' => [
					'name'      => $oCon->prefixOption( 'license_key' ),
					'maxlength' => $this->getDef( 'license_key_length' ),
				]
			],
			'ajax'    => [
				'license_handling' => $this->getAjaxActionData( 'license_handling' ),
				'connection_debug' => $this->getAjaxActionData( 'connection_debug' )
			],
			'aHrefs'  => [
				'shield_pro_url'           => 'https://shsec.io/shieldpro',
				'shield_pro_more_info_url' => 'https://shsec.io/shld1',
				'iframe_url'               => $this->getDef( 'landing_page_url' ),
				'keyless_cp'               => $this->getDef( 'keyless_cp' ),
			],
			'flags'   => [
				'show_ads'              => false,
				'button_enabled_check'  => true,
				'show_standard_options' => false,
				'show_alt_content'      => true,
				'is_pro'                => $this->isPremium()
			],
			'strings' => $this->getStrings()->getDisplayStrings(),
		];
	}

	/**
	 * @return string
	 */
	protected function getNamespaceBase() {
		return 'License';
	}
}