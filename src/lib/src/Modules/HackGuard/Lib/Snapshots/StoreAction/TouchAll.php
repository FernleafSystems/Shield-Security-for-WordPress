<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\FindAssetsToSnap;
use FernleafSystems\Wordpress\Services\Services;

class TouchAll extends BaseBulk {

	public function run() {
		foreach ( ( new FindAssetsToSnap() )->setMod( $this->getMod() )->run() as $asset ) {
			try {
				$oStore = ( new Load() )
					->setMod( $this->getMod() )
					->setAsset( $asset )
					->run();
				foreach ( [ $oStore->getSnapStorePath(), $oStore->getSnapStoreMetaPath() ] as $path ) {
					Services::WpFs()->touch( $path );
				}
			}
			catch ( \Exception $e ) {
			}
		}
	}
}