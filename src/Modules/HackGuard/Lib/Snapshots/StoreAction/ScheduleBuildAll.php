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
		[ $needsBuild, $needsPromotion ] = $this->classifyAssets();

		foreach ( $needsBuild as $asset ) {
			try {
				$this->buildMissingAsset( $asset );
			}
			catch ( \Throwable $e ) {
				error_log( '[Build Asset] Notice: '.$e->getMessage() );
			}
		}

		foreach ( $needsPromotion as $asset ) {
			try {
				( new PromoteLocalBaseline() )
					->setAsset( $asset )
					->run();
			}
			catch ( \Throwable $e ) {
				error_log( '[Promote Asset Snapshot] Notice: '.$e->getMessage() );
			}
		}
	}

	/**
	 * @return array{0:array<int,WpPluginVo|WpThemeVo>,1:array<int,WpPluginVo|WpThemeVo>}
	 */
	private function classifyAssets() :array {
		$needsBuild = [];
		$needsPromotion = [];
		$now = Services::Request()->ts();

		foreach ( ( new FindAssetsToSnap() )->run() as $asset ) {
			try {
				$snapshot = ( new Load() )
					->setAsset( $asset )
					->run()
					->getUsableSnapshot();
			}
			catch ( \Throwable $e ) {
				$snapshot = null;
			}

			if ( $snapshot === null ) {
				$needsBuild[] = $asset;
			}
			elseif ( PromoteLocalBaseline::isDue( $snapshot, $now ) ) {
				$needsPromotion[] = $asset;
			}
		}

		return [ $needsBuild, $needsPromotion ];
	}

	/**
	 * @param WpPluginVo|WpThemeVo $asset
	 */
	private function buildMissingAsset( $asset ) :void {
		( new Build() )
			->setAsset( $asset )
			->run();

		$store = ( new Load() )
			->setAsset( $asset )
			->run();
		if ( !$store->isUsable() ) {
			return;
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
}
