<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Services\Services;

class Options extends Base\Options {

	public function getInstallationDays() :int {
		$installedAt = self::con()
						   ->getModule_Plugin()
						   ->getInstallDate();
		if ( empty( $installedAt ) ) {
			return 0;
		}
		return (int)\round( ( Services::Request()->ts() - $installedAt )/\DAY_IN_SECONDS );
	}

	public function isShowPluginNotices() :bool {
		return $this->isShowPromoAdminNotices();
	}

	public function isShowPromoAdminNotices() :bool {
		return self::con()
				   ->getModule_Plugin()
				   ->opts()
				   ->isOpt( 'enable_upgrade_admin_notice', 'Y' );
	}
}