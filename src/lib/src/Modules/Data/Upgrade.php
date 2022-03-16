<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Data;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\Lib\UpgradeReqLogsTable;

class Upgrade extends Base\Upgrade {

	protected function upgrade_1411() {
		( new UpgradeReqLogsTable() )
			->setMod( $this->getMod() )
			->execute();
	}
}