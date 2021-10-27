<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\CrowdSourced\SubmitHashes;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\FindAssetsToSnap;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\{
	WpPluginVo,
	WpThemeVo
};
use FernleafSystems\Wordpress\Services\Services;

class ScheduleBuildAll extends BaseBulk {

	public function build() {
		foreach ( $this->getAssetsThatNeedBuilt() as $asset ) {
			try {
				( new Build() )
					->setMod( $this->getMod() )
					->setAsset( $asset )
					->run();

				$store = ( new Load() )
					->setMod( $this->getMod() )
					->setAsset( $asset )
					->run();

				if ( $this->getCon()->isPremiumActive()
					 && $store->verify()
					 && ( $asset->asset_type === 'plugin' || !$asset->is_child ) ) {

					$meta = $store->getSnapMeta();
					if ( empty( $meta[ 'cs_hashes_at' ] ) ) {
						$meta[ 'cs_hashes_at' ] = Services::Request()->ts();
						if ( $store->setSnapMeta( $meta )->saveMeta() ) {
							( new SubmitHashes() )
								->setMod( $this->getMod() )
								->run( $asset );
						}
					}
				}
			}
			catch ( \Exception $e ) {
				error_log( '[Build Asset] Notice: '.$e->getMessage() );
			}
		}
	}

	public function schedule() {
		if ( count( $this->getAssetsThatNeedBuilt() ) > 0 ) {
			$hook = $this->getCronHook();
			if ( wp_next_scheduled( $hook ) === false ) {
				wp_schedule_single_event( Services::Request()->ts() + 60, $hook );
			}
			if ( is_main_network() && wp_next_scheduled( $this->getCronHook() ) !== false ) {
				add_action( $this->getCronHook(), [ $this, 'build' ] );
			}
		}
	}

	/**
	 * Only those that don't have a meta file or the versions are different
	 * @return WpPluginVo[]|WpThemeVo[]
	 */
	private function getAssetsThatNeedBuilt() :array {
		return array_filter(

			( new FindAssetsToSnap() )
				->setMod( $this->getMod() )
				->run(),

			function ( $asset ) {
				try {
					$meta = ( new Load() )
						->setMod( $this->getMod() )
						->setAsset( $asset )
						->run()
						->getSnapMeta();
				}
				catch ( \Exception $e ) {
					$meta = null;
				}
				return ( empty( $meta ) || $asset->version !== $meta[ 'version' ] );
			}
		);
	}

	private function getCronHook() :string {
		return $this->getCon()->prefix( 'ptg_build_snapshots' );
	}

	/**
	 * @deprecated 13.0
	 */
	public function hookBuild() {
	}
}