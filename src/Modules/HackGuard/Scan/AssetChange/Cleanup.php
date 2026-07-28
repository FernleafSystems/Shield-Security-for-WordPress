<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\AssetChange;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes\{
	AssetTrustResolver,
	Retrieve
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\{
	WpPluginVo,
	WpThemeVo
};
use FernleafSystems\Wordpress\Services\Services;

class Cleanup {

	use PluginControllerConsumer;

	public const CRON_DELAY = 60;
	private const MAX_RETRIES = 1;

	public function getHook() :string {
		return self::con()->prefix( 'afs_asset_change_cleanup' );
	}

	public function schedule( string $assetType, string $assetKey, int $delay = self::CRON_DELAY, int $retry = 0 ) :bool {
		unset( $retry );
		[ $assetType, $assetKey ] = $this->normalizeAsset( $assetType, $assetKey );
		if ( $assetType === '' || $assetKey === '' ) {
			return false;
		}
		return self::con()->comps->asset_coordinator->enqueueAsset( $assetType, $assetKey, $delay );
	}

	public function run( $assetType = null, $assetKey = null, $retry = 0 ) :void {
		if ( !\is_string( $assetType ) || !\is_string( $assetKey ) || !\is_int( $retry )
			 || $retry < 0 || $retry > self::MAX_RETRIES ) {
			return;
		}
		$this->process( $assetType, $assetKey );
	}

	public function process( string $assetType, string $assetKey ) :bool {
		[ $assetType, $assetKey ] = $this->normalizeAsset( $assetType, $assetKey );
		if ( $assetType === '' || $assetKey === '' ) {
			return false;
		}

		$readiness = $this->prepareAssetForScan( $assetType, $assetKey );
		if ( !$readiness[ 'ready' ] ) {
			return false;
		}

		if ( $readiness[ 'reset_memoization' ] ) {
			Retrieve::resetMemoization();
			AssetTrustResolver::resetMemoization();
		}
		return self::con()->comps->scans->startAfsAssetScan( $assetType, $assetKey );
	}

	/**
	 * @return array{ready:bool, reset_memoization:bool}
	 */
	private function prepareAssetForScan( string $assetType, string $assetKey ) :array {
		if ( $assetType === 'core' ) {
			try {
				return [
					'ready'             => Services::CoreFileHashes()->isReady(),
					'reset_memoization' => false,
				];
			}
			catch ( \Throwable $e ) {
				return [
					'ready'             => false,
					'reset_memoization' => false,
				];
			}
		}

		$asset = $this->loadAsset( $assetType, $assetKey );
		if ( empty( $asset ) ) {
			return [
				'ready'             => true,
				'reset_memoization' => false,
			];
		}

		if ( ( new Retrieve() )->byVOFromStoredSnapshot( $asset ) !== null ) {
			return [
				'ready'             => true,
				'reset_memoization' => false,
			];
		}

		try {
			( new StoreAction\Build() )
				->setAsset( $asset )
				->run();

			$store = ( new StoreAction\Load() )
				->setAsset( $asset )
				->run();

			$ready = $store->isUsable();
			return [
				'ready'             => $ready,
				'reset_memoization' => $ready,
			];
		}
		catch ( \Throwable $e ) {
			return [
				'ready'             => false,
				'reset_memoization' => false,
			];
		}
	}

	/**
	 * @return null|WpPluginVo|WpThemeVo
	 */
	private function loadAsset( string $assetType, string $assetKey ) {
		return $assetType === 'plugin'
			? Services::WpPlugins()->getPluginAsVo( $assetKey, true )
			: Services::WpThemes()->getThemeAsVo( $assetKey, true );
	}

	/**
	 * @return array{0:string,1:string}
	 */
	private function normalizeAsset( string $assetType, string $assetKey ) :array {
		$assetType = \in_array( $assetType, [ 'core', 'plugin', 'theme' ], true ) ? $assetType : '';
		$assetKey = $assetType === 'core' ? 'core' : trim( $assetKey );

		return [ $assetType, $assetKey ];
	}
}
