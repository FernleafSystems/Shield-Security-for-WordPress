<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\License;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities;

class ICWP_WPSF_FeatureHandler_License extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	/**
	 * @var License\Lib\LicenseHandler
	 */
	private $oLicHandler;

	/**
	 * @var License\Lib\WpHashes\ApiTokenManager
	 */
	private $oWpHashesTokenManager;

	/**
	 * @return License\Lib\LicenseHandler
	 */
	public function getLicenseHandler() {
		if ( !isset( $this->oLicHandler ) ) {
			$this->oLicHandler = ( new Shield\Modules\License\Lib\LicenseHandler() )->setMod( $this );
		}
		return $this->oLicHandler;
	}

	/**
	 * @return License\Lib\WpHashes\ApiTokenManager
	 */
	public function getWpHashesTokenManager() {
		if ( !isset( $this->oWpHashesTokenManager ) ) {
			$this->oWpHashesTokenManager = ( new Shield\Modules\License\Lib\WpHashes\ApiTokenManager() )->setMod( $this );
		}
		return $this->oWpHashesTokenManager;
	}

	/**
	 * @return bool
	 */
	protected function isEnabledForUiSummary() {
		return $this->getLicenseHandler()->hasValidWorkingLicense();
	}

	public function buildInsightsVars() {
		$oWp = Services::WpGeneral();
		$oCon = $this->getCon();
		$oCarbon = Services::Request()->carbon();

		$oCurrent = $this->getLicenseHandler()->getLicense();

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
			'product_name'    => $oCurrent->is_central ?
				$this->getDef( 'license_item_name_sc' ) :
				$this->getDef( 'license_item_name' ),
			'license_active'  => $this->getLicenseHandler()->hasValidWorkingLicense() ?
				__( '&#10004;', 'wp-simple-firewall' ) : __( '&#10006;', 'wp-simple-firewall' ),
			'license_expires' => $sExpiresAt,
			'license_email'   => $oCurrent->customer_email,
			'last_checked'    => $sChecked,
			'last_errors'     => $this->hasLastErrors() ? $this->getLastErrors( true ) : '',
			'wphashes_token'  => $this->getWpHashesTokenManager()->hasToken() ?
				__( '&#10004;', 'wp-simple-firewall' ) : __( '&#10006;', 'wp-simple-firewall' ),
			'installation_id' => $oCon->getSiteInstallationId(),
		];
		return [
			'vars'    => [
				'license_table'  => $aLicenseTableVars,
				'activation_url' => $oWp->getHomeUrl(),
				'error'          => $this->getLastErrors( true )
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
				'is_pro'                => $this->isPremium(),
				'has_error'             => $this->hasLastErrors()
			],
			'strings' => $this->getStrings()->getDisplayStrings(),
		];
	}

	protected function redirectToInsightsSubPage() {
		Services::Response()->redirect(
			$this->getCon()->getModule_Insights()->getUrl_AdminPage(),
			[ 'inav' => 'license' ]
		);
	}

	public function runHourlyCron() {
		$this->getWpHashesTokenManager()->getToken();
	}

	protected function setupCustomHooks() {
		add_action( 'wp_loaded', [ $this, 'onWpLoaded' ] );
	}

	/**
	 * @deprecated 8.6.2
	 */
	protected function updateHandler() {
		$this->getWpHashesTokenManager()->getToken();
	}

	public function onWpLoaded() {
		$this->getWpHashesTokenManager()->run();
	}

	/**
	 * @return bool
	 */
	public function getIfShowModuleMenuItem() {
		return parent::getIfShowModuleMenuItem() && !$this->isPremium();
	}

	public function onPluginShutdown() {
		try {
			$this->getLicenseHandler()->verify( false );
		}
		catch ( Exception $oE ) {
		}
		parent::onPluginShutdown();
	}

	/**
	 * @return Shield\License\EddLicenseVO
	 * @deprecated 8.6.2
	 */
	protected function loadLicense() {
		return $this->getLicenseHandler()->getLicense();
	}

	/**
	 * @return array
	 * @deprecated 8.6.2
	 */
	protected function getLicenseData() {
		$aData = $this->getOpt( 'license_data', [] );
		return is_array( $aData ) ? $aData : [];
	}

	/**
	 * @return $this
	 * @deprecated 8.6.2
	 */
	public function clearLicenseData() {
		return $this->setOpt( 'license_data', [] );
	}

	/**
	 * @param Utilities\Licenses\EddLicenseVO $oLic
	 * @return $this
	 * @deprecated 8.6.2
	 */
	protected function setLicenseData( $oLic ) {
		return $this->setOpt( 'license_data', $oLic->getRawDataAsArray() );
	}

	/**
	 * @param string $sDeactivatedReason
	 * @deprecated 8.6.2
	 */
	public function deactivate( $sDeactivatedReason = '' ) {
	}

	/**
	 * License check normally only happens when the verification_at expires (~3 days)
	 * for a currently valid license.
	 * @param bool $bForceCheck
	 * @return $this
	 * @deprecated 8.6.2
	 */
	public function verifyLicense( $bForceCheck = true ) {
		try {
			$this->getLicenseHandler()->verify( $bForceCheck );
		}
		catch ( Exception $oE ) {
		}
		return $this;
	}

	/**
	 * @return bool
	 * @deprecated 8.6.2
	 */
	private function isLicenseCheckRequired() {
		return false;
	}

	/**
	 * @return bool
	 * @deprecated 8.6.2
	 */
	private function canLicenseCheck() {
		return false;
	}

	/**
	 * @return bool
	 * @deprecated 8.6.2
	 */
	private function canLicenseCheck_FileFlag() {
		return false;
	}

	/**
	 * @return $this
	 * @deprecated 8.6.2
	 */
	private function touchLicenseCheckFileFlag() {
		Services::WpFs()->touch( $this->getCon()->getPath_Flags( 'license_check' ) );
		return $this;
	}

	/**
	 * @return bool
	 * @deprecated 8.6.2
	 */
	protected function isLicenseMaybeExpiring() {
		return false;
	}

	/**
	 * @return $this
	 * @deprecated 8.6.2
	 */
	protected function activateLicense() {
		return $this;
	}

	/**
	 * @deprecated 8.6.2
	 */
	protected function sendLicenseWarningEmail() {
	}

	/**
	 * @deprecated 8.6.2
	 */
	private function sendLicenseDeactivatedEmail() {
	}

	/**
	 * @return int
	 * @deprecated 8.6.2
	 */
	protected function getLicenseActivatedAt() {
		return $this->getOpt( 'license_activated_at' );
	}

	/**
	 * @return int
	 * @deprecated 8.6.2
	 */
	protected function getLicenseDeactivatedAt() {
		return $this->getOpt( 'license_deactivated_at' );
	}

	/**
	 * @return string
	 * @deprecated 8.6.2
	 */
	public function getLicenseItemName() {
		return $this->getLicenseHandler()->getLicense()->is_central ?
			$this->getDef( 'license_item_name_sc' ) :
			$this->getDef( 'license_item_name' );
	}

	/**
	 * @return int
	 * @deprecated 8.6.2
	 */
	protected function getLicenseLastCheckedAt() {
		return $this->getOpt( 'license_last_checked_at' );
	}

	/**
	 * @param int $nTimePeriod
	 * @return bool
	 * @deprecated 8.6.2
	 */
	private function getIsLicenseNotCheckedFor( $nTimePeriod ) {
		return false;
	}

	/**
	 * @return int
	 * @deprecated 8.6.2
	 */
	public function getLicenseNotCheckedForInterval() {
		return 0;
	}

	/**
	 * @return bool
	 * @deprecated 8.6.2
	 */
	public function isLicenseActive() {
		return $this->getLicenseHandler()->isActive();
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
	 * @deprecated 8.6.2
	 */
	public function hasValidWorkingLicense() {
		return $this->getLicenseHandler()->hasValidWorkingLicense();
	}

	/**
	 * @return bool
	 * @deprecated 8.6.2
	 */
	protected function isLastVerifiedExpired() {
		return false;
	}

	/**
	 * @return bool
	 * @deprecated 8.6.2
	 */
	protected function isLastVerifiedGraceExpired() {
		return false;
	}

	/**
	 * @return bool
	 * @deprecated 8.6.2
	 */
	protected function isWithinVerifiedGraceExpired() {
		return false;
	}

	/**
	 * @param int $nAt
	 * @return $this
	 * @deprecated 8.6.2
	 */
	protected function setLicenseLastCheckedAt( $nAt = null ) {
		return $this;
	}

	/**
	 * @return string
	 */
	protected function getNamespaceBase() {
		return 'License';
	}
}