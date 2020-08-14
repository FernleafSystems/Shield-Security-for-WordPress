<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\FindAssetsToSnap;
use FernleafSystems\Wordpress\Services\Services;

class TouchAll extends BaseBulk {

	public function run() {
		foreach ( ( new FindAssetsToSnap() )->setMod( $this->getMod() )->run() as $oAsset ) {
			try {
				$oStore = ( new Load() )
					->setMod( $this->getMod() )
					->setAsset( $oAsset )
					->run();
				foreach ( [ $oStore->getSnapStorePath(), $oStore->getSnapStoreMetaPath() ] as $sPath ) {
					Services::WpFs()->touch( $sPath );
				}
			}
			catch ( \Exception $oE ) {
			}
		}
	}
}