<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\FindingsModel;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\{
	ResultItemMeta\Ops as ResultItemMetaDB,
	ResultItems\Ops as ResultItemsDB,
	ScanResults\Ops as ScanResultsDB
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\WpOrg;

class LegacyReconcile {

	use PluginControllerConsumer;

	public const BATCH_SIZE = 25;

	public function finishIfComplete() :void {
		$state = new State();
		if ( !$state->hasLegacyRows() ) {
			$state->markReady();
			$this->enqueueFollowUpScans();
		}
	}

	public function hasLegacyRows() :bool {
		return ( new State() )->hasLegacyRows();
	}

	public function nextLegacyIDs( int $limit = self::BATCH_SIZE ) :array {
		$limit = \max( 1, $limit );
		$ids = Services::WpDb()->selectCustom(
			sprintf(
				"SELECT `id`
					FROM `%s`
					WHERE `scan`=''
					ORDER BY `id` ASC
					LIMIT %d;",
				self::con()->db_con->scan_result_items->getTable(),
				$limit
			)
		);

		return \array_map(
			static fn( $row ) :int => (int)( $row[ 'id' ] ?? 0 ),
			\is_array( $ids ) ? $ids : []
		);
	}

	public function processBatch( int $limit = self::BATCH_SIZE ) :void {
		foreach ( $this->nextLegacyIDs( $limit ) as $resultItemID ) {
			if ( $resultItemID > 0 ) {
				$this->reconcileById( $resultItemID );
			}
		}
		$this->finishIfComplete();
	}

	public function reconcileById( int $resultItemID ) :void {
		$dbCon = self::con()->db_con;
		/** @var ?ResultItemsDB\Record $record */
		$record = $dbCon->scan_result_items->getQuerySelector()->byId( $resultItemID );
		if ( !( $record instanceof ResultItemsDB\Record ) || $record->scan !== '' ) {
			return;
		}

		$scanGroups = $this->loadScanGroups( $resultItemID );
		if ( empty( $scanGroups ) ) {
			$dbCon->scan_result_items->getQueryDeleter()->deleteById( $resultItemID );
			return;
		}

		$meta = $this->loadMeta( $resultItemID );
		$rawData = $record->getRawData();

		$first = true;
		foreach ( $scanGroups as $scanSlug => $group ) {
			$update = $this->buildReconciledData( $rawData, $scanSlug, $group[ 'last_seen_at' ], $meta );

			if ( $first ) {
				$dbCon->scan_result_items->getQueryUpdater()->updateById( $resultItemID, $update );
				$first = false;
				continue;
			}

			$newRecord = $dbCon->scan_result_items->getRecord();
			$newRecord->applyFromArray( \array_merge( $rawData, $update ), [ 'id' ] );
			$dbCon->scan_result_items->getQueryInserter()->insert( $newRecord );
			$newID = (int)Services::WpDb()->getVar( 'SELECT LAST_INSERT_ID()' );
			if ( $newID > 0 ) {
				$this->cloneMeta( $resultItemID, $newID, $meta );
				$this->repointScanResults( $resultItemID, $newID, $group[ 'scan_result_ids' ] );
			}
		}
	}

	private function buildReconciledData( array $rawData, string $scanSlug, int $lastSeenAt, array $meta ) :array {
		[ 'asset_type' => $assetType, 'asset_key' => $assetKey ] = $this->resolveAsset( $scanSlug, $rawData, $meta );
		[ 'resolved_at' => $resolvedAt, 'resolution_reason' => $resolutionReason ] = $this->resolveResolution( $rawData );

		return [
			'scan'              => $scanSlug,
			'asset_type'        => $assetType,
			'asset_key'         => $assetKey,
			'last_seen_at'      => $lastSeenAt,
			'resolved_at'       => $resolvedAt,
			'resolution_reason' => $resolutionReason,
		];
	}

	private function cloneMeta( int $sourceResultItemID, int $targetResultItemID, array $meta ) :void {
		if ( empty( $meta ) ) {
			return;
		}

		$dbh = self::con()->db_con->scan_result_item_meta;
		foreach ( $meta as $metaKey => $metaValue ) {
			$dbh->getQueryInserter()->setInsertData( [
				'ri_ref'     => $targetResultItemID,
				'meta_key'   => $metaKey,
				'meta_value' => \is_scalar( $metaValue ) ? (string)$metaValue : \wp_json_encode( $metaValue ),
			] )->query();
		}
	}

	private function enqueueFollowUpScans() :void {
		$con = self::con();
		if ( !$con->comps->scans->getCanScansExecute() ) {
			return;
		}

		$con->comps->scans->startNewScans( [
			$con->comps->scans->AFS(),
			$con->comps->scans->APC(),
			$con->comps->scans->WPV(),
		] );
	}

	private function loadMeta( int $resultItemID ) :array {
		/** @var ResultItemMetaDB\Select $selector */
		$selector = self::con()->db_con->scan_result_item_meta->getQuerySelector();
		$metaRows = $selector->filterByResultItemRef( $resultItemID )->queryWithResult();

		$meta = [];
		foreach ( \is_array( $metaRows ) ? $metaRows : [] as $row ) {
			$meta[ $row->meta_key ] = $this->normaliseMetaValue( $row->meta_value );
		}
		return $meta;
	}

	private function loadScanGroups( int $resultItemID ) :array {
		$rows = Services::WpDb()->selectCustom(
			sprintf(
				"SELECT `sr`.`id`, `sr`.`created_at`, `scans`.`scan`
					FROM `%s` AS `sr`
					INNER JOIN `%s` AS `scans`
						ON `scans`.`id`=`sr`.`scan_ref`
					WHERE `sr`.`resultitem_ref`=%d
					ORDER BY `sr`.`created_at` ASC, `sr`.`id` ASC;",
				self::con()->db_con->scan_results->getTable(),
				self::con()->db_con->scans->getTable(),
				$resultItemID
			)
		);

		$groups = [];
		foreach ( \is_array( $rows ) ? $rows : [] as $row ) {
			$scanSlug = (string)( $row[ 'scan' ] ?? '' );
			if ( $scanSlug === '' ) {
				continue;
			}
			if ( !isset( $groups[ $scanSlug ] ) ) {
				$groups[ $scanSlug ] = [
					'last_seen_at'    => 0,
					'scan_result_ids' => [],
				];
			}
			$groups[ $scanSlug ][ 'last_seen_at' ] = \max( $groups[ $scanSlug ][ 'last_seen_at' ], (int)( $row[ 'created_at' ] ?? 0 ) );
			$groups[ $scanSlug ][ 'scan_result_ids' ][] = (int)( $row[ 'id' ] ?? 0 );
		}

		return $groups;
	}

	private function normaliseMetaValue( string $metaValue ) {
		$decoded = \json_decode( $metaValue, true );
		return \json_last_error() === \JSON_ERROR_NONE ? $decoded : $metaValue;
	}

	private function repointScanResults( int $sourceResultItemID, int $targetResultItemID, array $scanResultIDs ) :void {
		$scanResultIDs = \array_values( \array_filter( \array_map( '\intval', $scanResultIDs ) ) );
		if ( empty( $scanResultIDs ) ) {
			return;
		}

		Services::WpDb()->doSql(
			sprintf(
				"UPDATE `%s`
					SET `resultitem_ref`=%d
					WHERE `resultitem_ref`=%d
					  AND `id` IN (%s);",
				self::con()->db_con->scan_results->getTable(),
				$targetResultItemID,
				$sourceResultItemID,
				\implode( ',', $scanResultIDs )
			)
		);
	}

	private function resolveAsset( string $scanSlug, array $rawData, array $meta ) :array {
		$itemType = (string)( $rawData[ 'item_type' ] ?? '' );
		$itemID = (string)( $rawData[ 'item_id' ] ?? '' );

		if ( \in_array( $scanSlug, [ 'apc', 'wpv' ], true ) ) {
			return [
				'asset_type' => $itemType === ResultItemsDB\Handler::ITEM_TYPE_THEME ? 'theme' : 'plugin',
				'asset_key'  => $itemID,
			];
		}

		if ( !empty( $meta[ 'is_in_core' ] ) ) {
			return [
				'asset_type' => 'core',
				'asset_key'  => 'core',
			];
		}
		if ( !empty( $meta[ 'is_in_plugin' ] ) && !empty( $meta[ 'ptg_slug' ] ) ) {
			return [
				'asset_type' => 'plugin',
				'asset_key'  => (string)$meta[ 'ptg_slug' ],
			];
		}
		if ( !empty( $meta[ 'is_in_theme' ] ) && !empty( $meta[ 'ptg_slug' ] ) ) {
			return [
				'asset_type' => 'theme',
				'asset_key'  => (string)$meta[ 'ptg_slug' ],
			];
		}

		try {
			$path = path_join( wp_normalize_path( ABSPATH ), $itemID );
			$plugin = ( new WpOrg\Plugin\Files() )->findPluginFromFile( $path );
			if ( !empty( $plugin ) ) {
				return [
					'asset_type' => 'plugin',
					'asset_key'  => $plugin->file,
				];
			}
		}
		catch ( \Throwable $e ) {
		}

		try {
			$path = path_join( wp_normalize_path( ABSPATH ), $itemID );
			$theme = ( new WpOrg\Theme\Files() )->findThemeFromFile( $path );
			if ( !empty( $theme ) ) {
				return [
					'asset_type' => 'theme',
					'asset_key'  => $theme->stylesheet,
				];
			}
		}
		catch ( \Throwable $e ) {
		}

		return [
			'asset_type' => 'other',
			'asset_key'  => '',
		];
	}

	private function resolveResolution( array $rawData ) :array {
		$deletedAt = (int)( $rawData[ 'item_deleted_at' ] ?? 0 );
		if ( $deletedAt > 0 ) {
			return [
				'resolved_at'       => $deletedAt,
				'resolution_reason' => 'deleted',
			];
		}

		$repairedAt = (int)( $rawData[ 'item_repaired_at' ] ?? 0 );
		if ( $repairedAt > 0 ) {
			return [
				'resolved_at'       => $repairedAt,
				'resolution_reason' => 'repaired',
			];
		}

		return [
			'resolved_at'       => 0,
			'resolution_reason' => '',
		];
	}
}
