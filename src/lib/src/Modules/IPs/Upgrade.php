<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Upgrade extends Base\Upgrade {

	protected function upgrade_1600() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		if ( method_exists( $mod, 'runIpMigrator' ) ) {
			$mod->runIpMigrator();
		}
	}
}