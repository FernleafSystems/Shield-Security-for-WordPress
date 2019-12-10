<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\FindAssetsToSnap;
use FernleafSystems\Wordpress\Services\Services;

class TouchAll extends BaseBulk {

	/**
	 */
	public function run() {
		foreach ( ( new FindAssetsToSnap() )->setMod( $this->getMod() )->run() as $oAsset ) {
			try {
				$oStore = ( new Load() )
					->setMod( $this->getMod() )
					->setAsset( $oAsset )
					->run();
				$oFS = Services::WpFs();
				foreach ( [ $oStore->getSnapStorePath(), $oStore->getSnapStoreMetaPath() ] as $sPath ) {
					if ( $oFS->exists( $sPath ) ) {
						$oFS->touch( $sPath );
					}
				}
			}
			catch ( \Exception $oE ) {
			}
		}
	}
}