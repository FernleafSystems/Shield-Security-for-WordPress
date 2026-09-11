<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\FindAssetsToSnap;
use FernleafSystems\Wordpress\Services\Services;

class TouchAll {

	/**
	 * @return array{has_unusable:bool,has_due_promotions:bool,touches_succeeded:bool}
	 */
	public function run() :array {
		$hasUnusable = false;
		$hasDuePromotions = false;
		$touchesSucceeded = true;
		$now = Services::Request()->ts();
		foreach ( ( new FindAssetsToSnap() )->run() as $asset ) {
			try {
				$store = ( new Load() )
					->setAsset( $asset )
					->run();
				$snapshot = $store->getUsableSnapshot();
				if ( $snapshot === null ) {
					$hasUnusable = true;
					continue;
				}
				$hasDuePromotions = $hasDuePromotions
									|| PromoteLocalBaseline::isDue( $snapshot, $now );
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
			'has_unusable'       => $hasUnusable,
			'has_due_promotions' => $hasDuePromotions,
			'touches_succeeded'  => $touchesSucceeded,
		];
	}
}
