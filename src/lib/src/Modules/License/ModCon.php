<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License;

class ModCon extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield\ModCon {

	public const SLUG = 'license';

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
		return $this->licenseHandler ?? $this->licenseHandler = ( new Lib\LicenseHandler() )->setMod( $this );
	}

	public function getWpHashesTokenManager() :Lib\WpHashes\ApiTokenManager {
		return $this->wpHashesTokenManager ?? $this->wpHashesTokenManager = ( new Lib\WpHashes\ApiTokenManager() )->setMod( $this );
	}
}