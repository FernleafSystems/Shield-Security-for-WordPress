<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes\{
	AssetTrustResolver,
	Retrieve
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\CrowdSourced\SubmitHashes;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\FindAssetsToSnap;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\{
	WpPluginVo,
	WpThemeVo
};
use FernleafSystems\Wordpress\Services\Services;

class ScheduleBuildAll extends BaseExec {

	protected function canRun() :bool {
		return true;
	}

	protected function run() {
		self::con()->comps->asset_coordinator->discoverMissingSnapshots();
	}

	public function build() :void {
		foreach ( $this->getAssetsThatNeedBuilt() as $asset ) {
			try {
				( new Build() )
					->setAsset( $asset )
					->run();

				$store = ( new Load() )
					->setAsset( $asset )
					->run();
				if ( !$store->isUsable() ) {
					continue;
				}

				Retrieve::resetMemoization();
				AssetTrustResolver::resetMemoization();

				$canCrowdsource = $asset instanceof WpPluginVo
					? \dirname( $asset->file ) !== '.'
					: !( $asset->is_child || $asset->is_inactive_child );
				if ( self::con()->isPremiumActive() && $canCrowdsource ) {
					$meta = $store->getSnapMeta();
					if ( empty( $meta[ 'cs_hashes_at' ] ) ) {
						$meta[ 'cs_hashes_at' ] = Services::Request()->ts();
						if ( $store->setSnapMeta( $meta )->saveMeta() ) {
							( new SubmitHashes() )->run( $asset );
						}
					}
				}
			}
			catch ( \Throwable $e ) {
				error_log( '[Build Asset] Notice: '.$e->getMessage() );
			}
		}
	}

	/**
	 * @return array<int,WpPluginVo|WpThemeVo> Installed assets without a usable exact-version snapshot.
	 */
	public function getAssetsThatNeedBuilt() :array {
		return \array_filter(
			( new FindAssetsToSnap() )->run(),
			function ( $asset ) {
				try {
					$store = ( new Load() )
						->setAsset( $asset )
						->run();
					$needBuilt = !$store->isUsable();
				}
				catch ( \Throwable $e ) {
					$needBuilt = true;
				}
				return $needBuilt;
			}
		);
	}
}
