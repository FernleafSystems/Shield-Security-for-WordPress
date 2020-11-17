<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Upgrade extends Base\Upgrade {

	protected function runEveryUpgrade() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$mod->deleteAllPluginCrons();
	}
}