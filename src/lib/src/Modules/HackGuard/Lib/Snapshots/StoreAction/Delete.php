<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction;

use FernleafSystems\Wordpress\Services\Services;

class Delete extends BaseAction {

	public function run() {
		try {
			$oStore = ( new Load() )
				->setMod( $this->getMod() )
				->setAsset( $this->getAsset() )
				->run();
			foreach ( [ $oStore->getSnapStorePath(), $oStore->getSnapStoreMetaPath() ] as $sPath ) {
				Services::WpFs()->deleteFile( $sPath );
			}
		}
		catch ( \Exception $oE ) {
		}
	}
}