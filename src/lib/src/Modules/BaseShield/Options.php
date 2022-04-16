<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Services\Services;

class Options extends Base\Options {

	public function getInstallationDays() :int {
		$nTimeInstalled = $this->getCon()
							   ->getModule_Plugin()
							   ->getInstallDate();
		if ( empty( $nTimeInstalled ) ) {
			return 0;
		}
		return (int)round( ( Services::Request()->ts() - $nTimeInstalled )/DAY_IN_SECONDS );
	}

	public function isPremium() :bool {
		return $this->getCon()->isPremiumActive();
	}

	public function isShowPluginNotices() :bool {
		return $this->isShowPromoAdminNotices();
	}

	public function isShowPromoAdminNotices() :bool {
		return $this->getCon()
					->getModule_Plugin()
					->getOptions()
					->isOpt( 'enable_upgrade_admin_notice', 'Y' );
	}
}