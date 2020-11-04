<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Services\Services;

class ModCon extends BaseShield\ModCon {

	/**
	 * @var Lib\LicenseHandler
	 */
	private $oLicHandler;

	/**
	 * @var Lib\WpHashes\ApiTokenManager
	 */
	private $oWpHashesTokenManager;

	/**
	 * @return Lib\LicenseHandler
	 */
	public function getLicenseHandler() :Lib\LicenseHandler {
		if ( !isset( $this->oLicHandler ) ) {
			$this->oLicHandler = ( new Lib\LicenseHandler() )->setMod( $this );
		}
		return $this->oLicHandler;
	}

	public function getWpHashesTokenManager() :Lib\WpHashes\ApiTokenManager {
		if ( !isset( $this->oWpHashesTokenManager ) ) {
			$this->oWpHashesTokenManager = ( new Lib\WpHashes\ApiTokenManager() )->setMod( $this );
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

	public function getIfShowModuleMenuItem() :bool {
		return parent::getIfShowModuleMenuItem() && !$this->isPremium();
	}

	public function onPluginShutdown() {
		try {
			$this->getLicenseHandler()->verify( false );
		}
		catch ( \Exception $e ) {
		}
		parent::onPluginShutdown();
	}
}