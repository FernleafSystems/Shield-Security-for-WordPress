<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots;
use FernleafSystems\Wordpress\Services\Services;

class Load extends Base {

	/**
	 * @return Snapshots\Store
	 * @throws \Exception
	 */
	public function run() {
		$oStore = $this->getNewStore();

		foreach ( [ $oStore->getSnapStorePath(), $oStore->getSnapStoreMetaPath() ] as $sPath ) {
			if ( !Services::WpFs()->exists( $sPath ) ) {
				throw new \Exception( 'Critical store file does not exist: '.$sPath );
			}
		}
		return $oStore;
	}
}