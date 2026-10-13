<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes\{
	AssetTrustResolver,
	FileHashAlgorithm,
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

	public function build( string $algorithm = 'md5' ) :void {
		FileHashAlgorithm::validate( $algorithm );
		[ $needsBuild, $needsPromotion ] = $this->classifyAssets();

		foreach ( $needsBuild as $asset ) {
			try {
				$this->buildMissingAsset( $asset, $algorithm );
			}
			catch ( \Throwable $e ) {
				error_log( '[Build Asset] Notice: '.$e->getMessage() );
			}
		}

		foreach ( $needsPromotion as $asset ) {
			try {
				( new PromoteLocalBaseline() )
					->setAsset( $asset )
					->run( $algorithm );
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
	private function buildMissingAsset( $asset, string $algorithm ) :void {
		( new Build() )
			->setAsset( $asset )
			->run( $algorithm );

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
