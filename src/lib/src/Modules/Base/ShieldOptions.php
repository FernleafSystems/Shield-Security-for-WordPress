<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Wordpress\Services\Services;

class ShieldOptions extends Options {

	/**
	 * @return int
	 */
	public function getInstallationDays() {
		$nTimeInstalled = $this->getCon()
							   ->getModule_Plugin()
							   ->getInstallDate();
		if ( empty( $nTimeInstalled ) ) {
			return 0;
		}
		return (int)round( ( Services::Request()->ts() - $nTimeInstalled )/DAY_IN_SECONDS );
	}

	/**
	 * @return bool
	 */
	public function isPremium() {
		return $this->getCon()->isPremiumActive();
	}

	/**
	 * @return bool
	 */
	public function isShowPromoAdminNotices() {
		return $this->getCon()
					->getModule_Plugin()
					->getOptions()
					->isOpt( 'enable_upgrade_admin_notice', 'Y' );
	}
}