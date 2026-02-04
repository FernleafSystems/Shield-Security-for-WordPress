<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\FindAssetsToSnap;
use FernleafSystems\Wordpress\Services\Services;

class TouchAll extends BaseExec {

	public function run() {
		foreach ( ( new FindAssetsToSnap() )->run() as $asset ) {
			try {
				$store = ( new Load() )
					->setAsset( $asset )
					->run();
				foreach ( [ $store->getSnapStorePath(), $store->getSnapStoreMetaPath() ] as $path ) {
					Services::WpFs()->touch( $path );
				}
			}
			catch ( \Exception $e ) {
			}
		}
	}
}