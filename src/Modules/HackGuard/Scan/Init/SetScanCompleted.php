<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Scans\Ops as ScansDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

/**
 * TODO: not the most efficient
 */
class SetScanCompleted {

	use PluginControllerConsumer;

	public function run( int $scanID ) {
		$con = self::con();
		$dbCon = $con->db_con;
		$count = (int)Services::WpDb()->getVar(
			sprintf( "SELECT count(*)
						FROM `%s` as `si`
						WHERE `si`.`scan_ref` = %d
						  AND `si`.`finished_at`=0;",
				$dbCon->scan_items->getTable(),
				$scanID
			)
		);

		if ( $count === 0 ) {
			$scanRecord = $dbCon->scans->getQuerySelector()->byId( $scanID );
			if ( empty( $scanRecord ) ) {
				return;
			}
			$now = Services::Request()->ts();
			( new \FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue\RunState() )->markCompleted( $scanID );

			$this->resolveStaleItemsForRun( $scanID, $scanRecord, $now );

			$scanCon = $con->comps->scans->getScanCon( $scanRecord->scan );
			$con->comps->events->fireEvent( 'scan_run', [
				'audit_params' => [
					'scan' => $scanCon->getScanName()
				]
			] );

			$this->auditLatestScanItems( $scanCon, $scanID );
		}
	}

	/**
	 * @param Base $scanCon
	 */
	private function auditLatestScanItems( $scanCon, int $scanID ) {
		$resultItemIDs = self::con()->db_con->scan_results->getQuerySelector()
						 ->filterByScan( $scanID )
						 ->getDistinctForColumn( 'resultitem_ref' );
		$results = empty( $resultItemIDs )
			? $scanCon->getNewResultsSet()
			: ( new \FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\RetrieveItems() )
				->setScanController( $scanCon )
				->byIDs( $resultItemIDs );

		if ( $results->countItems() > 0 ) {

			$items = $results->countItems() > 30 ?
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
		$observedItemIDs = self::con()->db_con->scan_results->getQuerySelector()
						 ->filterByScan( $scanID )
						 ->getDistinctForColumn( 'resultitem_ref' );
		$notInObserved = empty( $observedItemIDs )
			? ''
			: sprintf( " AND `id` NOT IN (%s)", implode( ',', array_map( 'intval', $observedItemIDs ) ) );
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
					  %s;",
				self::con()->db_con->scan_result_items->getTable(),
				$resolvedAt,
				$reason,
				$scanSlug,
				$scopeWhere,
				$notInObserved
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
}
