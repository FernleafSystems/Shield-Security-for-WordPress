<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\AssetChange;

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
		[ $assetType, $assetKey ] = $this->normalizeAsset( $assetType, $assetKey );
		if ( $assetType === '' || $assetKey === '' ) {
			return false;
		}

		if ( $this->hasPendingCleanup( $assetType, $assetKey ) ) {
			return true;
		}

		$args = [ $assetType, $assetKey, $retry ];
		return \wp_schedule_single_event( Services::Request()->ts() + $delay, $this->getHook(), $args ) !== false;
	}

	public function run( string $assetType, string $assetKey, int $retry = 0 ) :void {
		[ $assetType, $assetKey ] = $this->normalizeAsset( $assetType, $assetKey );
		if ( $assetType === '' || $assetKey === '' ) {
			return;
		}

		$this->resolveReplacedFindings( $assetType, $assetKey );

		if ( !$this->ensureAssetReadyForScan( $assetType, $assetKey ) ) {
			if ( $retry < self::MAX_RETRIES ) {
				$this->schedule( $assetType, $assetKey, self::CRON_DELAY, $retry + 1 );
			}
			return;
		}

		self::con()->comps->scans->startAfsAssetScan( $assetType, $assetKey );
	}

	private function resolveReplacedFindings( string $assetType, string $assetKey ) :void {
		$dbCon = self::con()->db_con;
		$now = Services::Request()->ts();

		Services::WpDb()->doSql(
			sprintf(
				"UPDATE `%s`
					SET `resolved_at`=%d,
						`resolution_reason`='asset_replaced'
					WHERE `scan`='afs'
					  AND `resolved_at`=0
					  AND `asset_type`='%s'
					  AND `asset_key`='%s'
					  AND EXISTS (
						SELECT 1
						FROM `%s` AS `rim`
						WHERE `rim`.`ri_ref`=`%s`.`id`
						  AND `rim`.`meta_key` IN ('is_checksumfail','is_missing')
						  AND `rim`.`meta_value`!=''
						  AND `rim`.`meta_value`!='0'
					  );",
				$dbCon->scan_result_items->getTable(),
				$now,
				esc_sql( $assetType ),
				esc_sql( $assetKey ),
				$dbCon->scan_result_item_meta->getTable(),
				$dbCon->scan_result_items->getTable()
			)
		);
		self::con()->comps->scans->resetScanResultsCountMemoization();
	}

	private function ensureAssetReadyForScan( string $assetType, string $assetKey ) :bool {
		if ( $assetType === 'core' ) {
			try {
				return Services::CoreFileHashes()->isReady();
			}
			catch ( \Throwable $e ) {
				return false;
			}
		}

		$asset = $this->loadAsset( $assetType, $assetKey );
		if ( empty( $asset ) ) {
			return true;
		}

		try {
			( new StoreAction\Build() )
				->setAsset( $asset )
				->run();

			$store = ( new StoreAction\Load() )
				->setAsset( $asset )
				->run();

			return $store->verify() && \count( $store->getSnapData() ) > 0;
		}
		catch ( \Throwable $e ) {
			return false;
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

	private function hasPendingCleanup( string $assetType, string $assetKey ) :bool {
		$pending = false;
		foreach ( \range( 0, self::MAX_RETRIES ) as $retry ) {
			if ( \wp_next_scheduled( $this->getHook(), [ $assetType, $assetKey, $retry ] ) !== false ) {
				$pending = true;
				break;
			}
		}
		return $pending;
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
