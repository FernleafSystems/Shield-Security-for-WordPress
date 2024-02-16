<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;

use FernleafSystems\Wordpress\Services\Services;

class Upgrade extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Upgrade {

	protected function upgradeCommon() {
		parent::upgradeCommon();
		$SP = Services::ServiceProviders();
		if ( \method_exists( $SP, 'clearProviders' ) ) {
			$SP->clearProviders();
		}
	}
}