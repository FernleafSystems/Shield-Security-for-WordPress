<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction;

use FernleafSystems\Wordpress\Services\Services;

class Delete extends BaseAction {

	public function run() {
		try {
			$store = ( new Load() )
				->setAsset( $this->getAsset() )
				->run();
			foreach ( [ $store->getSnapStorePath(), $store->getSnapStoreMetaPath() ] as $path ) {
				Services::WpFs()->deleteFile( $path );
			}
		}
		catch ( \Exception $e ) {
		}
	}
}