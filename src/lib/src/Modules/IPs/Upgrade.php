<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\IPs\Delete;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Upgrade extends Base\Upgrade {

	protected function upgrade_1201() {
		( new Lib\Ops\ConvertLegacy() )
			->setMod( $this->getMod() )
			->run();
	}

	protected function upgrade_1010() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var Delete $del */
		$del = $mod->getDbHandler_IPs()->getQueryDeleter();
		$del->filterByLabel( 'iControlWP' )->query();
	}
}