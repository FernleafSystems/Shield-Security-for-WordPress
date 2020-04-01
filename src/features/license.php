<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\License;
use FernleafSystems\Wordpress\Services\Services;

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
	public function getProcessor() {
		return $this->getLicenseHandler();
	}

	/**
	 * @return License\Lib\LicenseHandler
	 */
	public function getLicenseHandler() {
		if ( !isset( $this->oLicHandler ) ) {
			$this->oLicHandler = ( new License\Lib\LicenseHandler() )->setMod( $this );
		}
		return $this->oLicHandler;
	}

	/**
	 * @return License\Lib\WpHashes\ApiTokenManager
	 */
	public function getWpHashesTokenManager() {
		if ( !isset( $this->oWpHashesTokenManager ) ) {
			$this->oWpHashesTokenManager = ( new License\Lib\WpHashes\ApiTokenManager() )->setMod( $this );
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
	 * @return string
	 */
	protected function getNamespaceBase() {
		return 'License';
	}
}