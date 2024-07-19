<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License;

class ModCon extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\ModCon {

	public const SLUG = \FernleafSystems\Wordpress\Plugin\Shield\Enum\EnumModules::LICENSE;

	/**
	 * @deprecated 19.2
	 */
	public function getLicenseHandler() :Lib\LicenseHandler {
		return self::con()->comps->license;
	}
}