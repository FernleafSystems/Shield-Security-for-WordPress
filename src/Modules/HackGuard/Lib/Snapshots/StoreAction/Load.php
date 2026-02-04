<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots;
use FernleafSystems\Wordpress\Services\Services;

class Load extends BaseAction {

	/**
	 * @throws \Exception
	 */
	public function run() :Snapshots\Store {
		$store = $this->getNewStore();

		foreach ( [ $store->getSnapStorePath(), $store->getSnapStoreMetaPath() ] as $path ) {
			if ( !Services::WpFs()->exists( $path ) ) {
				throw new \Exception( __( 'Critical store file does not exist: ', 'wp-simple-firewall' ).$path );
			}
		}
		return $store;
	}
}
