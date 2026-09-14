<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Scans\Ops as ScansDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue\QueueItemVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\ScanStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\ScanActionVO;
use FernleafSystems\Wordpress\Services\Services;

class SetScanCompleted {
	use PluginControllerConsumer;

	private const ASSET_ELIGIBILITY_SQL_CHUNK_SIZE = 100;

	public function runForQueueItem( QueueItemVO $queueItem ): bool {
		return $this->run( $queueItem->scan_id );
	}

	public function run( int $scanID, ?ScansDB\Record $scanRecord = null, bool $persistScanMeta = false ): bool {
		$con = self::con();
		$dbCon = $con->db_con;
		$now = Services::Request()->ts();
		$metaUpdate = '';
		$expectedEncodedMeta = null;
		if ( $persistScanMeta && !empty( $scanRecord ) ) {
			$raw = $scanRecord->getRawData();
			if ( isset( $raw[ 'meta' ] ) ) {
				$expectedEncodedMeta = (string)$raw[ 'meta' ];
				$metaUpdate = \sprintf( ", `meta`='%s'", esc_sql( $raw[ 'meta' ] ) );
			}
		}

		$completed = (int)Services::WpDb()->doSql(
				sprintf( "UPDATE `%s`
						SET `finished_at`=%d,
							`status`='%s',
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
					ScanStatus::COMPLETED,
					$now,
					$metaUpdate,
					$scanID,
					$dbCon->scan_items->getTable(),
					$scanID
				)
			) > 0;

		if ( !$completed ) {
			if ( $persistScanMeta ) {
				throw new \RuntimeException( 'Empty scan completion and metadata persistence failed.' );
			}
			return false;
		}

		/** @var ?ScansDB\Record $scanRecord */
		$scanRecord = $dbCon->scans->getQuerySelector()->byId( $scanID );
		if ( $persistScanMeta ) {
			$persistedRaw = empty( $scanRecord ) ? [] : $scanRecord->getRawData();
			if ( $expectedEncodedMeta === null
				 || !isset( $persistedRaw[ 'meta' ] )
				 || !\hash_equals( $expectedEncodedMeta, (string)$persistedRaw[ 'meta' ] ) ) {
				throw new \RuntimeException( 'Empty scan metadata persistence verification failed.' );
			}
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

			$itemDescriptions = \array_slice( \array_unique(
				\array_map( fn( $item ) => $item->getDescriptionForAudit(), $results->getAllItems() )
			), 0, 30 );

			$items .= ' "'.\implode( '", "', $itemDescriptions ).'"';

			self::con()->comps->events->fireEvent( 'scan_items_found', [
				'audit_params' => [
					'scan'  => $scanCon->getScanName(),
					'items' => $items
				]
			] );
		}
	}

	private function resolveStaleItemsForRun( int $scanID, ScansDB\Record $scanRecord, int $resolvedAt ): void {
		$scanSlug = \preg_replace( '/[^a-z0-9_]/i', '', $scanRecord->scan ) ?? '';
		$scopeWhere = $this->buildScopeWhere( $scanRecord );
		$coverageWheres = $this->buildCoverageWheres( $scanRecord );
		if ( empty( $coverageWheres ) ) {
			return;
		}
		$reason = $scanSlug === 'afs'
		          && \in_array( $scanRecord->scope_type, [ 'core', 'plugin', 'theme' ], true )
		          && $scanRecord->run_trigger === 'asset_change'
			? 'asset_replaced'
			: 'clean_rescan';

		$hasAffectedRows = false;
		try {
			$this->clearStaleMalwareMarkersForFullAfsRun( $scanID, $scanRecord, $hasAffectedRows );
			foreach ( $coverageWheres as $coverageWhere ) {
				$affectedRows = Services::WpDb()->doSql(
					sprintf(
						"UPDATE `%s`
							SET `resolved_at`=%d,
								`resolution_reason`='%s'
							WHERE `scan`='%s'
							  AND `resolved_at`=0
							  %s
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
						$coverageWhere,
						self::con()->db_con->scan_results->getTable(),
						$scanID,
						self::con()->db_con->scan_result_items->getTable()
					)
				);
				if ( $affectedRows === false ) {
					throw new \RuntimeException( 'Stale scan result reconciliation failed.' );
				}
				$hasAffectedRows = $hasAffectedRows || (int)$affectedRows > 0;
			}
		}
		finally {
			if ( $hasAffectedRows ) {
				self::con()->comps->scans->resetScanResultsCountMemoization();
			}
		}
	}

	/**
	 * @return list<string>
	 */
	private function buildCoverageWheres( ScansDB\Record $scanRecord ) :array {
		if ( $scanRecord->scan !== 'afs' ) {
			return [ '' ];
		}

		$meta = $scanRecord->meta;
		$families = \is_array( $meta ) ? ( $meta[ 'coverage_families' ] ?? null ) : null;
		if ( !$this->isValidCoverageFamilies( $families ) ) {
			return [];
		}

		if ( $scanRecord->scope_type !== 'full' ) {
			$coverageWhere = $this->buildCoverageWhereForBranches(
				$this->buildCoveredOwnershipHaving( $families, [
					ScanActionVO::COVERAGE_FAMILY_CORE_INTEGRITY   => 'is_in_core',
					ScanActionVO::COVERAGE_FAMILY_PLUGIN_INTEGRITY => 'is_in_plugin',
					ScanActionVO::COVERAGE_FAMILY_THEME_INTEGRITY  => 'is_in_theme',
				] ),
				$this->buildCoveredUnidentifiedLocationHaving( $families ),
				\in_array( ScanActionVO::COVERAGE_FAMILY_MALWARE, $families, true )
			);
			return $coverageWhere === null ? [] : [ $coverageWhere ];
		}

		$coverageWheres = [];
		$baseCoverageWhere = $this->buildCoverageWhereForBranches(
			\in_array( ScanActionVO::COVERAGE_FAMILY_CORE_INTEGRITY, $families, true )
				? $this->buildExactOwnershipHaving( 'is_in_core' )
				: '',
			$this->buildCoveredUnidentifiedLocationHaving( $families ),
			\in_array( ScanActionVO::COVERAGE_FAMILY_MALWARE, $families, true ),
			true
		);
		if ( $baseCoverageWhere !== null ) {
			$coverageWheres[] = $baseCoverageWhere;
		}

		$action = ( new ScanActionVO() )->applyFromArray( $meta );
		if ( !$action->hasValidAssetSnapshotEligibility() ) {
			return $coverageWheres;
		}

		foreach ( \array_chunk(
			$action->getComparisonEligibleAssetTuples(),
			self::ASSET_ELIGIBILITY_SQL_CHUNK_SIZE
		) as $chunk ) {
			$assetCoverageWhere = $this->buildCoverageWhereForBranches(
				$this->buildEligibleAssetOwnershipHaving( $families, $chunk ),
				'',
				false
			);
			if ( $assetCoverageWhere !== null ) {
				$coverageWheres[] = $assetCoverageWhere;
			}
		}

		return $coverageWheres;
	}

	/**
	 * @param list<string> $families
	 */
	private function buildCoveredUnidentifiedLocationHaving( array $families ) :string {
		return $this->buildCoveredOwnershipHaving( $families, [
			ScanActionVO::COVERAGE_FAMILY_WPROOT_UNIDENTIFIED    => 'is_in_wproot',
			ScanActionVO::COVERAGE_FAMILY_WPCONTENT_UNIDENTIFIED => 'is_in_wpcontent',
		] );
	}

	private function buildCoverageWhereForBranches(
		string $integrityOwnership,
		string $unidentifiedLocation,
		bool $includeMalware,
		bool $malwareOnly = false
	) :?string {
		$unrecognised = $this->truthyMetaAggregate( 'is_unrecognised' );
		$malware = $this->truthyMetaAggregate( 'is_mal' );
		$missing = $this->truthyMetaAggregate( 'is_missing' );
		$checksumFail = $this->truthyMetaAggregate( 'is_checksumfail' );
		$unidentified = $this->truthyMetaAggregate( 'is_unidentified' );
		$coveredIssues = [];
		if ( $integrityOwnership !== '' ) {
			$coveredIssues[] = \sprintf( '(%s=1 AND (%s))', $unrecognised, $integrityOwnership );
		}
		if ( $includeMalware ) {
			$coveredIssues[] = $malwareOnly
				? \sprintf(
					'(%s=0 AND %s=0 AND %s=0 AND %s=0 AND %s=1)',
					$unrecognised,
					$missing,
					$checksumFail,
					$unidentified,
					$malware
				)
				: \sprintf( '(%s=0 AND %s=1)', $unrecognised, $malware );
		}
		if ( $integrityOwnership !== '' ) {
			$coveredIssues[] = \sprintf(
				'(%s=0 AND %s=0 AND %s=1 AND (%s))',
				$unrecognised,
				$malware,
				$missing,
				$integrityOwnership
			);
			$coveredIssues[] = \sprintf(
				'(%s=0 AND %s=0 AND %s=0 AND %s=1 AND (%s))',
				$unrecognised,
				$malware,
				$missing,
				$checksumFail,
				$integrityOwnership
			);
		}
		if ( $unidentifiedLocation !== '' ) {
			$coveredIssues[] = \sprintf(
				'(%s=0 AND %s=0 AND %s=0 AND %s=0 AND %s=1 AND (%s))',
				$unrecognised,
				$malware,
				$missing,
				$checksumFail,
				$unidentified,
				$unidentifiedLocation
			);
		}
		if ( empty( $coveredIssues ) ) {
			return null;
		}

		return \sprintf(
			" AND EXISTS (
				SELECT 1
				FROM `%s` AS `rim_coverage`
				WHERE `rim_coverage`.`ri_ref`=`%s`.`id`
				GROUP BY `rim_coverage`.`ri_ref`
				HAVING %s
			  )",
			self::con()->db_con->scan_result_item_meta->getTable(),
			self::con()->db_con->scan_result_items->getTable(),
			\implode( ' OR ', $coveredIssues )
		);
	}

	private function clearStaleMalwareMarkersForFullAfsRun(
		int $scanID,
		ScansDB\Record $scanRecord,
		bool &$didMutate
	) :void {
		if ( $scanRecord->scan !== 'afs' || $scanRecord->scope_type !== 'full' ) {
			return;
		}

		$meta = $scanRecord->meta;
		$families = \is_array( $meta ) ? ( $meta[ 'coverage_families' ] ?? null ) : null;
		if ( !$this->isValidCoverageFamilies( $families )
			 || !\in_array( ScanActionVO::COVERAGE_FAMILY_MALWARE, $families, true ) ) {
			return;
		}

		$dbCon = self::con()->db_con;
		$resultItemsTable = $dbCon->scan_result_items->getTable();
		$metaTable = $dbCon->scan_result_item_meta->getTable();
		global $wpdb;
		$rows = Services::WpDb()->selectCustom( \sprintf(
			"SELECT `ri`.`id`
				FROM `%s` AS `ri`
				INNER JOIN `%s` AS `rim_coverage` ON `rim_coverage`.`ri_ref`=`ri`.`id`
				WHERE `ri`.`scan`='afs'
				  AND `ri`.`resolved_at`=0
				  AND NOT EXISTS (
					SELECT 1
					FROM `%s` AS `sr`
					WHERE `sr`.`scan_ref`=%d
					  AND `sr`.`resultitem_ref`=`ri`.`id`
				  )
				GROUP BY `ri`.`id`
				HAVING %s=1
				   AND (%s=1 OR %s=1 OR %s=1 OR %s=1);",
			$resultItemsTable,
			$metaTable,
			$dbCon->scan_results->getTable(),
			$scanID,
			$this->truthyMetaAggregate( 'is_mal' ),
			$this->truthyMetaAggregate( 'is_unrecognised' ),
			$this->truthyMetaAggregate( 'is_missing' ),
			$this->truthyMetaAggregate( 'is_checksumfail' ),
			$this->truthyMetaAggregate( 'is_unidentified' )
		) );
		if ( !\is_array( $rows )
			 || ( \is_object( $wpdb ) && (string)( $wpdb->last_error ?? '' ) !== '' ) ) {
			throw new \RuntimeException( 'Stale malware marker lookup failed.' );
		}

		$resultItemIDs = \array_values( \array_unique( \array_filter( \array_map(
			static fn( array $row ) :int => (int)( $row[ 'id' ] ?? 0 ),
			$rows
		) ) ) );
		foreach ( \array_chunk( $resultItemIDs, self::ASSET_ELIGIBILITY_SQL_CHUNK_SIZE ) as $chunk ) {
			$updated = Services::WpDb()->doSql( \sprintf(
				"UPDATE `%s`
					SET `meta_value`=0
					WHERE `ri_ref` IN (%s)
					  AND `meta_key`='is_mal'
					  AND `meta_value`!=''
					  AND `meta_value`!='0';",
				$metaTable,
				\implode( ',', $chunk )
			) );
			if ( $updated === false ) {
				throw new \RuntimeException( 'Stale malware marker clear failed.' );
			}
			$didMutate = $didMutate || (int)$updated > 0;
		}
	}

	/**
	 * @param list<string> $families
	 * @param list<array{0:string,1:string,2:string}> $eligibleAssets
	 */
	private function buildEligibleAssetOwnershipHaving( array $families, array $eligibleAssets ) :string {
		$covered = [];
		$resultItemsTable = self::con()->db_con->scan_result_items->getTable();
		foreach ( $eligibleAssets as $eligibleAsset ) {
			[ $assetType, $assetKey, $assetVersion ] = $eligibleAsset;
			$family = $assetType === 'plugin'
				? ScanActionVO::COVERAGE_FAMILY_PLUGIN_INTEGRITY
				: ScanActionVO::COVERAGE_FAMILY_THEME_INTEGRITY;
			if ( !\in_array( $family, $families, true ) ) {
				continue;
			}

			$ownership = [];
			foreach ( [
				'is_in_core'   => false,
				'is_in_plugin' => $assetType === 'plugin',
				'is_in_theme'  => $assetType === 'theme',
			] as $flag => $required ) {
				$ownership[] = \sprintf(
					'%s=%d',
					$this->truthyMetaAggregate( $flag ),
					$required ? 1 : 0
				);
			}

			$covered[] = \sprintf(
				"(%s
				  AND BINARY `%s`.`asset_type`=BINARY '%s'
				  AND BINARY `%s`.`asset_key`=BINARY '%s'
				  AND BINARY %s=BINARY '%s')",
				\implode( ' AND ', $ownership ),
				$resultItemsTable,
				esc_sql( $assetType ),
				$resultItemsTable,
				esc_sql( $assetKey ),
				$this->assetVersionMetaAggregate(),
				esc_sql( $assetVersion )
			);
		}
		return \implode( ' OR ', $covered );
	}

	private function assetVersionMetaAggregate() :string {
		return "MAX(CASE
			WHEN `rim_coverage`.`meta_key`='asset_version' THEN `rim_coverage`.`meta_value`
			ELSE ''
		END)";
	}

	private function isValidCoverageFamilies( $families ) :bool {
		if ( !\is_array( $families ) || empty( $families )
			 || \array_keys( $families ) !== \range( 0, \count( $families ) - 1 ) ) {
			return false;
		}

		foreach ( $families as $family ) {
			if ( !\is_string( $family ) || !\in_array( $family, ScanActionVO::COVERAGE_FAMILIES, true ) ) {
				return false;
			}
		}

		return \count( \array_unique( $families, \SORT_STRING ) ) === \count( $families );
	}

	/**
	 * @param list<string> $families
	 * @param array<string,string> $familyFlags
	 */
	private function buildCoveredOwnershipHaving( array $families, array $familyFlags ) :string {
		$covered = [];
		foreach ( $familyFlags as $family => $selectedFlag ) {
			if ( !\in_array( $family, $families, true ) ) {
				continue;
			}

			$covered[] = $this->buildExactFlagsHaving( $selectedFlag, \array_values( $familyFlags ) );
		}

		return \implode( ' OR ', $covered );
	}

	private function buildExactOwnershipHaving( string $selectedFlag ) :string {
		return $this->buildExactFlagsHaving(
			$selectedFlag,
			[ 'is_in_core', 'is_in_plugin', 'is_in_theme' ]
		);
	}

	/**
	 * @param list<string> $flags
	 */
	private function buildExactFlagsHaving( string $selectedFlag, array $flags ) :string {
		$ownership = [];
		foreach ( $flags as $flag ) {
			$ownership[] = \sprintf(
				'%s=%d',
				$this->truthyMetaAggregate( $flag ),
				$flag === $selectedFlag ? 1 : 0
			);
		}
		return '('.\implode( ' AND ', $ownership ).')';
	}

	private function truthyMetaAggregate( string $metaKey ) :string {
		return \sprintf(
			"MAX(CASE
				WHEN `rim_coverage`.`meta_key`='%s'
				  AND `rim_coverage`.`meta_value`!=''
				  AND `rim_coverage`.`meta_value`!='0'
				THEN 1
				ELSE 0
			END)",
			esc_sql( $metaKey )
		);
	}

	private function buildScopeWhere( ScansDB\Record $scanRecord ): string {
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

		if ( $scanRecord->scope_type === 'core' ) {
			return sprintf(
				" AND `asset_type`='core' AND `asset_key`='core'
				  AND EXISTS (
					SELECT 1
					FROM `%s` AS `rim_scope`
					WHERE `rim_scope`.`ri_ref`=`%s`.`id`
					  AND `rim_scope`.`meta_key` IN ('is_checksumfail','is_missing')
					  AND `rim_scope`.`meta_value`!=''
					  AND `rim_scope`.`meta_value`!='0'
				  )",
				self::con()->db_con->scan_result_item_meta->getTable(),
				self::con()->db_con->scan_result_items->getTable()
			);
		}

		return '';
	}

	private function resultItemIDsForScan( int $scanID ): array {
		return \array_values( \array_unique( \array_filter( \array_map(
			static fn( $record ): int => (int)( \is_array( $record ) ? ( $record[ 'resultitem_ref' ] ?? 0 ) : ( $record->resultitem_ref ?? 0 ) ),
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
