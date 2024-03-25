<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

use FernleafSystems\Wordpress\Services\Services;

/**
 * @deprecated 19.1
 */
class Options extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Options {

	/**
	 * @deprecated 19.1
	 */
	public function getInstallationDays() :int {
		$installedAt = self::con()
						   ->getModule_Plugin()
						   ->getInstallDate();
		if ( empty( $installedAt ) ) {
			return 0;
		}
		return (int)\round( ( Services::Request()->ts() - $installedAt )/\DAY_IN_SECONDS );
	}

	/**
	 * @deprecated 19.1
	 */
	public function isShowPromoAdminNotices() :bool {
		return self::con()->getModule_Plugin()->opts()->isOpt( 'enable_upgrade_admin_notice', 'Y' );
	}
}