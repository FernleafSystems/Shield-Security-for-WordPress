<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots;
use FernleafSystems\Wordpress\Services\Services;

class Load extends BaseAction {

	/**
	 * @return Snapshots\Store
	 * @throws \Exception
	 */
	public function run() {
		$store = $this->getNewStore();

		foreach ( [ $store->getSnapStorePath(), $store->getSnapStoreMetaPath() ] as $path ) {
			if ( !Services::WpFs()->exists( $path ) ) {
				throw new \Exception( 'Critical store file does not exist: '.$path );
			}
		}
		return $store;
	}
}