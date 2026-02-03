<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots;

class CreateNew extends BaseAction {

	/**
	 * Will delete any existing stores for the asset
	 * @throws \Exception
	 */
	public function run() :Snapshots\Store {
		( new Delete() )
			->setAsset( $this->getAsset() )
			->run();
		return $this->getNewStore();
	}
}