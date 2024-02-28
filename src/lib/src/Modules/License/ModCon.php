<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License;

class ModCon extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\ModCon {

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
		return isset( self::con()->comps ) ? self::con()->comps->license :
			( $this->licenseHandler ?? $this->licenseHandler = new Lib\LicenseHandler() );
	}

	public function getWpHashesTokenManager() :Lib\WpHashes\ApiTokenManager {
		return isset( self::con()->comps ) ? self::con()->comps->api_token :
			( $this->wpHashesTokenManager ?? $this->wpHashesTokenManager = new Lib\WpHashes\ApiTokenManager() );
	}
}