<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\FindAssetsToSnap;
use FernleafSystems\Wordpress\Services\Services;

class TouchAll extends Base {

	public function run() {
		if ( $this->isTempDirAvailable() ) {
			foreach ( ( new FindAssetsToSnap() )->setMod( $this->getMod() )->run() as $asset ) {
				try {
					$store = ( new Load() )
						->setMod( $this->getMod() )
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
}