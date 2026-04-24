<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Scans\Ops as ScansDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue\QueueItemVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class SetScanCompleted {

	use PluginControllerConsumer;

	public function runForQueueItem( QueueItemVO $queueItem ) :bool {
		$scanRecord = new ScansDB\Record();
		$scanRecord->id = $queueItem->scan_id;
		$scanRecord->scan = $queueItem->scan;
		$scanRecord->scope_type = $queueItem->scope_type;
		$scanRecord->scope_key = $queueItem->scope_key;
		$scanRecord->run_trigger = $queueItem->run_trigger;
		return $this->run( $queueItem->scan_id, $scanRecord );
	}

	public function run( int $scanID, ?ScansDB\Record $scanRecord = null, bool $persistScanMeta = false ) :bool {
		$con = self::con();
		$dbCon = $con->db_con;
		$now = Services::Request()->ts();
		$metaUpdate = '';
		if ( $persistScanMeta && !empty( $scanRecord ) ) {
			$raw = $scanRecord->getRawData();
			if ( isset( $raw[ 'meta' ] ) ) {
				$metaUpdate = \sprintf( ", `meta`='%s'", esc_sql( $raw[ 'meta' ] ) );
			}
		}

		$completed = (int)Services::WpDb()->doSql(
			sprintf( "UPDATE `%s`
						SET `finished_at`=%d,
							`status`='completed',
							`last_process_at`=%d
							%s
						WHERE `id`=%d
						  AND `finished_at`=0
						  AND NOT EXISTS (
							SELECT 1
							FROM `%s` as `si`
							WHERE `si`.`scan_ref`=%d
							  AND `si`.`finished_at`=0
						  );",
				$dbCon->scans->getTable(),
				$now,
				$now,
				$metaUpdate,
				$scanID,
				$dbCon->scan_items->getTable(),
				$scanID
			)
		) > 0;

		if ( !$completed ) {
			return false;
		}

		if ( empty( $scanRecord ) ) {
			$scanRecord = $dbCon->scans->getQuerySelector()->byId( $scanID );
		}
		if ( empty( $scanRecord ) ) {
			return true;
		}

		try {
			$this->resolveStaleItemsForRun( $scanID, $scanRecord, $now );

			$scanCon = $con->comps->scans->getScanCon( $scanRecord->scan );
			$con->comps->events->fireEvent( 'scan_run', [
				'audit_params' => [
					'scan' => $scanCon->getScanName()
				]
			] );

			$this->auditLatestScanItems( $scanCon, $scanID );
		}
		catch ( \Throwable $e ) {
			error_log( \sprintf(
				'Shield scan completion side effect failed: scan_id=%d message=%s',
				$scanID,
				$e->getMessage()
			) );
		}

		return true;
	}

	/**
	 * @param Base $scanCon
	 */
	private function auditLatestScanItems( $scanCon, int $scanID ) {
		$resultItemIDs = $this->resultItemIDsForScan( $scanID );
		$auditItemIDs = \array_slice( $resultItemIDs, 0, 30 );
		$results = empty( $resultItemIDs )
			? $scanCon->getNewResultsSet()
			: ( new \FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\RetrieveItems() )
				->setScanController( $scanCon )
				->byIDs( $auditItemIDs );

		if ( $results->countItems() > 0 ) {

			$items = \count( $resultItemIDs ) > 30 ?
				__( 'Only the first 30 items are shown.', 'wp-simple-firewall' )
				: __( 'The following items were discovered.', 'wp-simple-firewall' );

			$itemDescriptions = \array_slice( \array_unique( \array_map( function ( $item ) {
				return $item->getDescriptionForAudit();
			}, $results->getItems() ) ), 0, 30 );

			$items .= ' "'.\implode( '", "', $itemDescriptions ).'"';

			self::con()->comps->events->fireEvent( 'scan_items_found', [
				'audit_params' => [
					'scan'  => $scanCon->getScanName(),
					'items' => $items
				]
			] );
		}
	}

	private function resolveStaleItemsForRun( int $scanID, ScansDB\Record $scanRecord, int $resolvedAt ) :void {
		$scanSlug = \preg_replace( '/[^a-z0-9_]/i', '', $scanRecord->scan ) ?? '';
		$scopeWhere = $this->buildScopeWhere( $scanRecord );
		$reason = $scanSlug === 'afs'
			&& \in_array( $scanRecord->scope_type, [ 'plugin', 'theme' ], true )
			&& $scanRecord->run_trigger === 'asset_change'
				? 'asset_replaced'
				: 'clean_rescan';

		Services::WpDb()->doSql(
			sprintf(
				"UPDATE `%s`
					SET `resolved_at`=%d,
						`resolution_reason`='%s'
					WHERE `scan`='%s'
					  AND `resolved_at`=0
					  %s
					  AND NOT EXISTS (
						SELECT 1
						FROM `%s` as `sr`
						WHERE `sr`.`scan_ref`=%d
						  AND `sr`.`resultitem_ref`=`%s`.`id`
					  );",
				self::con()->db_con->scan_result_items->getTable(),
				$resolvedAt,
				$reason,
				$scanSlug,
				$scopeWhere,
				self::con()->db_con->scan_results->getTable(),
				$scanID,
				self::con()->db_con->scan_result_items->getTable()
			)
		);
	}

	private function buildScopeWhere( ScansDB\Record $scanRecord ) :string {
		if ( $scanRecord->scan !== 'afs' || $scanRecord->scope_type === 'full' ) {
			return '';
		}

		if ( \in_array( $scanRecord->scope_type, [ 'plugin', 'theme' ], true ) ) {
			return sprintf(
				" AND `asset_type`='%s' AND `asset_key`='%s'",
				esc_sql( $scanRecord->scope_type ),
				esc_sql( $scanRecord->scope_key )
			);
		}

		return '';
	}

	private function resultItemIDsForScan( int $scanID ) :array {
		return \array_values( \array_unique( \array_filter( \array_map(
			static fn( $record ) :int => (int)( \is_array( $record ) ? ( $record[ 'resultitem_ref' ] ?? 0 ) : ( $record->resultitem_ref ?? 0 ) ),
			Services::WpDb()->selectCustom(
				sprintf( "SELECT DISTINCT `resultitem_ref`
							FROM `%s`
							WHERE `scan_ref`=%d
							ORDER BY `resultitem_ref` ASC
							LIMIT 31;",
					self::con()->db_con->scan_results->getTable(),
					$scanID
				)
			) ?: []
		) ) ) );
	}
}
