<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Services\Services;

class ModCon extends BaseShield\ModCon {

	/**
	 * @var Lib\LicenseHandler
	 */
	private $licenseHandler;

	/**
	 * @var Lib\WpHashes\ApiTokenManager
	 */
	private $wpHashesTokenManager;

	/**
	 * @return Lib\LicenseHandler
	 */
	public function getLicenseHandler() :Lib\LicenseHandler {
		if ( !isset( $this->licenseHandler ) ) {
			$this->licenseHandler = ( new Lib\LicenseHandler() )->setMod( $this );
		}
		return $this->licenseHandler;
	}

	public function getWpHashesTokenManager() :Lib\WpHashes\ApiTokenManager {
		if ( !isset( $this->wpHashesTokenManager ) ) {
			$this->wpHashesTokenManager = ( new Lib\WpHashes\ApiTokenManager() )->setMod( $this );
		}
		return $this->wpHashesTokenManager;
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

	public function onWpLoaded() {
		parent::onWpLoaded();
		try {
			$this->getLicenseHandler()->verify( false );
		}
		catch ( \Exception $e ) {
		}
	}
}