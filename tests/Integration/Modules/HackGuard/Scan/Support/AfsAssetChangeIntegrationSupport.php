<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Modules\HackGuard\Scan\Support;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue\QueueItemVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Store;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\ScanStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Services\Services;

trait AfsAssetChangeIntegrationSupport {

	/**
	 * @return array{
	 *   asset_type:string,
	 *   asset_key:string,
	 *   scope_type:string,
	 *   scope_key:string,
	 *   path_full:string,
	 *   stale_path_full:string,
	 *   digest_slug:string,
	 *   meta:array<string,int|string>
	 * }
	 */
	protected function afsAssetScenario( string $assetType ) :array {
		if ( $assetType === 'plugin' ) {
			$assetKey = self::con()->base_file;
			$pathFull = \wp_normalize_path( WP_PLUGIN_DIR.'/'.$assetKey );
			return [
				'asset_type'      => 'plugin',
				'asset_key'       => $assetKey,
				'scope_type'      => 'plugin',
				'scope_key'       => $assetKey,
				'path_full'       => $pathFull,
				'stale_path_full' => \wp_normalize_path( WP_PLUGIN_DIR.'/'.\dirname( $assetKey ).'/asset-replaced-stale.php' ),
				'digest_slug'     => 'afs_plugin',
				'meta'            => [
					'is_in_plugin'    => 1,
					'is_checksumfail' => 1,
					'ptg_slug'        => $assetKey,
				],
			];
		}

		if ( $assetType === 'theme' ) {
			$assetKey = 'shield-integration-theme';
			$pathFull = \wp_normalize_path( WP_CONTENT_DIR.'/themes/'.$assetKey.'/style.php' );
			return [
				'asset_type'      => 'theme',
				'asset_key'       => $assetKey,
				'scope_type'      => 'theme',
				'scope_key'       => $assetKey,
				'path_full'       => $pathFull,
				'stale_path_full' => \wp_normalize_path( WP_CONTENT_DIR.'/themes/'.$assetKey.'/asset-replaced-stale.php' ),
				'digest_slug'     => 'afs_theme',
				'meta'            => [
					'is_in_theme'     => 1,
					'is_checksumfail' => 1,
					'ptg_slug'        => $assetKey,
				],
			];
		}

		if ( $assetType === 'core' ) {
			$pathFull = \wp_normalize_path( \path_join( ABSPATH, WPINC.'/version.php' ) );
			return [
				'asset_type'      => 'core',
				'asset_key'       => 'core',
				'scope_type'      => 'core',
				'scope_key'       => 'core',
				'path_full'       => $pathFull,
				'stale_path_full' => \wp_normalize_path( \path_join( ABSPATH, WPINC.'/asset-replaced-stale.php' ) ),
				'digest_slug'     => 'afs_wp',
				'meta'            => [
					'is_in_core'      => 1,
					'is_checksumfail' => 1,
				],
			];
		}

		throw new \InvalidArgumentException( \sprintf( 'Unsupported AFS asset scenario: %s', $assetType ) );
	}

	/**
	 * @param array<string,mixed> $scenario
	 * @param array<string,int|string>|null $meta
	 * @return array{scan_result_id:int,result_item_id:int,meta_ids:list<int>}
	 */
	protected function seedAfsFinding(
		int $scanID,
		array $scenario,
		string $pathFull,
		?array $meta = null
	) :array {
		return TestDataFactory::insertAfsFileScanResultTracked( $scanID, $pathFull, $meta ?? $scenario[ 'meta' ] );
	}

	/**
	 * @param array<string,mixed> $scenario
	 * @param array<string,int|string>|null $meta
	 */
	protected function storeAfsObservation( int $scanID, array $scenario, ?array $meta = null ) :void {
		( new Store() )->store( $this->newAfsQueueItem( $scanID ), [
			\array_merge( [
				'path_full'     => $scenario[ 'path_full' ],
				'path_fragment' => $scenario[ 'path_full' ],
				'file_path'     => $scenario[ 'path_full' ],
			], $meta ?? $scenario[ 'meta' ] ),
		] );
	}

	protected function newAfsQueueItem( int $scanID ) :QueueItemVO {
		$queueItem = new QueueItemVO();
		$queueItem->scan_id = $scanID;
		$queueItem->qitem_id = 0;
		$queueItem->scan = 'afs';
		return $queueItem;
	}

	/**
	 * @param list<string> $coverageFamilies
	 */
	protected function insertAfsScan(
		string $scopeType,
		string $scopeKey,
		array $coverageFamilies,
		string $runTrigger = 'asset_change'
	) :int {
		$dbh = self::con()->db_con->scans;
		$record = $dbh->getRecord();
		$record->scan = 'afs';
		$record->status = ScanStatus::RUNNING;
		$record->scope_type = $scopeType;
		$record->scope_key = $scopeKey;
		$record->run_trigger = $runTrigger;
		$record->started_at = \max( 1, Services::Request()->ts() - 60 );
		$record->last_process_at = Services::Request()->ts();
		$record->ready_at = \max( 1, Services::Request()->ts() - 60 );
		$record->finished_at = 0;
		$record->meta = [
			'coverage_families' => $coverageFamilies,
		];

		$this->assertTrue( $dbh->getQueryInserter()->insert( $record ) );
		return (int)$GLOBALS[ 'wpdb' ]->insert_id;
	}

	protected function countAfsResultItemsForPath( string $pathFragment ) :int {
		global $wpdb;
		return (int)$wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*)
				FROM `".self::con()->db_con->scan_result_items->getTable()."`
				WHERE `scan`=%s
				  AND `item_id`=%s",
			'afs',
			$pathFragment
		) );
	}

	protected function countAfsScanResultLinks( int $scanID, int $resultItemID ) :int {
		global $wpdb;
		return (int)$wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*)
				FROM `".self::con()->db_con->scan_results->getTable()."`
				WHERE `scan_ref`=%d
				  AND `resultitem_ref`=%d",
			$scanID,
			$resultItemID
		) );
	}
}
