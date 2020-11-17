<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\License;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @deprecated 10.1
 */
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

	protected function redirectToInsightsSubPage() {
		Services::Response()->redirect(
			$this->getCon()->getModule_Insights()->getUrl_AdminPage(),
			[ 'inav' => 'license' ]
		);
	}

	public function runHourlyCron() {
		$this->getWpHashesTokenManager()->getToken();
	}

	public function onWpInit() {
		parent::onWpInit();
		$this->getWpHashesTokenManager()->execute();
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
	protected function getNamespaceBase() :string {
		return 'License';
	}
}