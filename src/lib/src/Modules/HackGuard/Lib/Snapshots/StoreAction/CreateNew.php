<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots;

class CreateNew extends BaseAction {

	/**
	 * Will delete any existing stores for the asset
	 * @return Snapshots\Store
	 * @throws \Exception
	 */
	public function run() {
		( new Delete() )
			->setMod( $this->getMod() )
			->setAsset( $this->getAsset() )
			->run();
		return $this->getNewStore();
	}
}