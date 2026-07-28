<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\FindAssetsToSnap;
use FernleafSystems\Wordpress\Services\Services;

class TouchAll {

	/**
	 * @return array{has_unusable:bool,touches_succeeded:bool}
	 */
	public function run() :array {
		$hasUnusable = false;
		$touchesSucceeded = true;
		foreach ( ( new FindAssetsToSnap() )->run() as $asset ) {
			try {
				$store = ( new Load() )
					->setAsset( $asset )
					->run();
				if ( !$store->isUsable() ) {
					$hasUnusable = true;
					continue;
				}
			}
			catch ( \Throwable $e ) {
				$hasUnusable = true;
				continue;
			}

			try {
				foreach ( [ $store->getSnapStorePath(), $store->getSnapStoreMetaPath() ] as $path ) {
					if ( !Services::WpFs()->touch( $path ) ) {
						$touchesSucceeded = false;
					}
				}
			}
			catch ( \Throwable $e ) {
				$touchesSucceeded = false;
			}
		}

		return [
			'has_unusable'      => $hasUnusable,
			'touches_succeeded' => $touchesSucceeded,
		];
	}
}
