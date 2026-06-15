<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Sites;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ImportExportSites\Ops\{
	Handler as SitesDB,
	Record
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\WhitelistNotifyQueue;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class SiteRepository {

	use PluginControllerConsumer;

	public const MIGRATED_AT_OPTION = 'importexport_sites_migrated_at';
	public const OLD_NOTIFY_CRON = 'importexport_notify';
	public const OLD_QUEUE_ACTION = 'whitelist_notify_urls';
	private const SQL_BATCH_SIZE = 20;

	public function ensureLegacyImported( bool $includeOldQueueState = true ) :void {
		$dbh = $this->dbOrNull();
		if ( !( $dbh instanceof SitesDB ) || !$dbh->isReady() ) {
			return;
		}
		if ( !$this->hasConfigHandler() ) {
			return;
		}
		if ( (int)self::con()->opts->optGet( self::MIGRATED_AT_OPTION ) > 0 ) {
			return;
		}

		$fallbackUrls = $this->canonicalLegacyWhitelistUrls();
		$urlIds = $this->legacyImportIds();

		$now = Services::Request()->ts();
		$oldQueuedUrls = $includeOldQueueState ? $this->canonicalOldQueueUrls( $fallbackUrls ) : [];
		$existingRows = $this->findByUrls( $fallbackUrls, true );
		$insertRows = [];
		$updateRowsByHash = [];

		foreach ( $fallbackUrls as $url ) {
			$row = $existingRows[ $url ] ?? null;
			$markDue = \in_array( $url, $oldQueuedUrls, true );
			$importID = (string)( $urlIds[ \hash( 'md5', $url ) ] ?? '' );
			$data = $this->buildActiveUpsertData(
				$row,
				$url,
				SitesDB::SOURCE_LEGACY_OPTION,
				$importID,
				$markDue,
				$now
			);

			if ( !$row instanceof Record ) {
				$insertRows[] = $this->buildActiveInsertData(
					$url,
					SitesDB::SOURCE_LEGACY_OPTION,
					$importID,
					$markDue,
					$now
				);
			}
			elseif ( $this->rowNeedsUpdate( $row, $data ) ) {
				$data[ 'updated_at' ] = $now;
				$updateRowsByHash[ $row->url_hash ] = $data;
			}
		}

		$this->bulkInsertRows( $insertRows );
		$this->bulkUpdateRowsByHash( $updateRowsByHash );

		self::con()->opts->optSet( self::MIGRATED_AT_OPTION, $now );
		$this->storeOptionsIfChanged();
		$this->clearOldQueueState();
	}

	public function canonicalizeUrl( string $url ) :string {
		$validated = Services::Data()->validateSimpleHttpUrl( $url );
		return $validated === false ? '' : (string)$validated;
	}

	public function urlHash( string $url ) :string {
		return \hash( 'md5', $this->canonicalizeUrl( $url ) );
	}

	public function upsertActive( string $url, string $source, string $importID = '', bool $markDue = false ) :?Record {
		$url = $this->canonicalizeUrl( $url );
		$dbh = $this->dbOrNull();
		if ( empty( $url ) || !( $dbh instanceof SitesDB ) || !$dbh->isReady() ) {
			return null;
		}

		$now = Services::Request()->ts();
		$row = $this->findByUrl( $url, true );
		$data = $this->buildActiveUpsertData( $row, $url, $source, $importID, $markDue, $now );

		if ( $row instanceof Record ) {
			if ( !$this->rowNeedsUpdate( $row, $data ) ) {
				return $row;
			}
			$data[ 'updated_at' ] = $now;
			$this->updateById( $row->id, $data );
			return $this->findById( $row->id, true );
		}

		$this->bulkInsertRows( [
			$this->buildActiveInsertData( $url, $source, $importID, $markDue, $now ),
		] );
		return $this->findByUrl( $url, true );
	}

	public function upsertPendingClientSite( string $url, string $source, bool $sendInvite ) :?Record {
		$url = $this->canonicalizeUrl( $url );
		$dbh = $this->dbOrNull();
		if ( empty( $url ) || !( $dbh instanceof SitesDB ) || !$dbh->isReady() ) {
			return null;
		}

		$now = Services::Request()->ts();
		$row = $this->findByUrl( $url, true );
		$data = $this->buildPendingClientSiteUpsertData( $row, $url, $source, $sendInvite, $now );

		if ( $row instanceof Record ) {
			if ( !$this->rowNeedsUpdate( $row, $data ) ) {
				return $row;
			}
			$data[ 'updated_at' ] = $now;
			$this->updateById( $row->id, $data );
			return $this->findById( $row->id, true );
		}

		$this->bulkInsertRows( [
			$this->buildPendingClientSiteInsertData( $url, $source, $sendInvite, $now ),
		] );
		return $this->findByUrl( $url, true );
	}

	public function softDeleteUrl( string $url ) :void {
		$row = $this->findByUrl( $url, true );
		if ( $row instanceof Record ) {
			$now = Services::Request()->ts();
			$this->updateById( $row->id, [
				'status'             => SitesDB::STATUS_DELETED,
				'queue_status'       => SitesDB::QUEUE_IDLE,
				'deleted_at'         => $now,
				'updated_at'         => $now,
				'lock_until'         => 0,
				'expected_export_by' => 0,
			] );
		}
	}

	public function queueSiteIds( array $ids ) :int {
		return $this->queueRows( $this->findActiveByIds( $ids ) );
	}

	public function deleteByIds( array $ids ) :int {
		$dbh = $this->dbOrNull();
		if ( !( $dbh instanceof SitesDB ) || !$dbh->isReady() ) {
			return 0;
		}

		$deleted = 0;
		foreach ( $this->sanitiseIds( $ids ) as $id ) {
			if ( $this->findById( $id, true ) instanceof Record
				 && $dbh->getQueryDeleter()->deleteById( $id ) ) {
				$deleted++;
			}
		}
		return $deleted;
	}

	public function queueAllActive() :int {
		return $this->queueRows( $this->selectActiveRows() );
	}

	/**
	 * @return Record[]
	 */
	public function claimDueRows( int $limit, int $lockUntil ) :array {
		$now = Services::Request()->ts();
		$rows = $this->selectDueRowsForClaim( $now, $limit );

		$data = [
			'queue_status' => SitesDB::QUEUE_PROCESSING,
			'picked_at'    => $now,
			'lock_until'   => $lockUntil,
		];
		$data = $this->withUpdatedAt( $data, $now );
		$this->bulkUpdateRowsByIds( \array_map( static fn( Record $row ) :int => $row->id, $rows ), $data );

		foreach ( $rows as $row ) {
			foreach ( $data as $key => $value ) {
				$row->{$key} = $value;
			}
		}

		return $rows;
	}

	/**
	 * @return Record[]
	 */
	public function claimDueInviteRows( int $limit, int $lockUntil ) :array {
		$now = Services::Request()->ts();
		$rows = $this->selectDueInviteRowsForClaim( $now, $limit );

		$data = [
			'picked_at'  => $now,
			'lock_until' => $lockUntil,
		];
		$data = $this->withUpdatedAt( $data, $now );
		$this->bulkUpdateRowsByIds( \array_map( static fn( Record $row ) :int => $row->id, $rows ), $data );

		foreach ( $rows as $row ) {
			foreach ( $data as $key => $value ) {
				$row->{$key} = $value;
			}
		}

		return $rows;
	}

	public function recoverExpiredProcessingRows( int $limit ) :int {
		$now = Services::Request()->ts();
		$rows = $this->selectExpiredProcessingRowsForRecovery( $now, $limit );
		if ( empty( $rows ) ) {
			return 0;
		}

		$this->bulkUpdateRowsByIds(
			\array_map( static fn( Record $row ) :int => $row->id, $rows ),
			$this->buildQueueDueData( $now )
		);

		return \count( $rows );
	}

	/**
	 * @return Record[]
	 */
	public function selectExpiredWaitingExportRows( int $limit ) :array {
		return $this->selectExpiredWaitingExportRowsWithSql( Services::Request()->ts(), $limit );
	}

	public function recordInviteProcessed( Record $row ) :void {
		$this->updateById( $row->id, [
			'queue_status'        => SitesDB::QUEUE_PENDING_CONNECTION,
			'consecutive_failures' => 0,
			'next_ping_at'        => 0,
			'lock_until'          => 0,
			'picked_at'           => 0,
			'expected_export_by'  => 0,
		] );
	}

	public function recordPingAttempt( Record $row ) :void {
		$now = Services::Request()->ts();
		$this->updateById( $row->id, [
			'last_ping_attempt_at' => $now,
			'ping_attempts_total'  => $row->ping_attempts_total + 1,
		] );
	}

	public function recordPingSuccess( Record $row, int $httpCode, int $expectedExportBy ) :void {
		$now = Services::Request()->ts();
		$this->updateById( $row->id, [
			'queue_status'          => SitesDB::QUEUE_WAITING_EXPORT,
			'last_ping_success_at'  => $now,
			'last_ping_http_code'   => $httpCode,
			'last_ping_error'       => '',
			'expected_export_by'    => $expectedExportBy,
			'lock_until'            => 0,
			'picked_at'             => 0,
		] );
	}

	public function recordPingFailure( Record $row, int $httpCode, string $error ) :void {
		$failures = $row->consecutive_failures + 1;
		$this->updateById( $row->id, [
			'queue_status'          => SitesDB::QUEUE_QUEUED,
			'last_ping_failure_at'  => Services::Request()->ts(),
			'last_ping_http_code'   => $httpCode,
			'last_ping_error'       => $this->trimError( $error ),
			'consecutive_failures'  => $failures,
			'next_ping_at'          => $this->nextRetryAt( $failures ),
			'lock_until'            => 0,
			'picked_at'             => 0,
			'expected_export_by'    => 0,
		] );
	}

	public function recordExportTimeout( Record $row ) :void {
		$failures = $row->consecutive_failures + 1;
		$this->updateById( $row->id, [
			'queue_status'             => SitesDB::QUEUE_QUEUED,
			'last_export_failure_at'   => Services::Request()->ts(),
			'last_export_result_code'  => SitesDB::EXPORT_RESULT_TIMEOUT,
			'last_export_error'        => 'export_not_requested_before_grace_window',
			'consecutive_failures'     => $failures,
			'next_ping_at'             => $this->nextRetryAt( $failures ),
			'expected_export_by'       => 0,
			'lock_until'               => 0,
			'picked_at'                => 0,
		] );
	}

	public function recordExportRequested( string $url ) :void {
		$row = $this->findByUrl( $url );
		if ( $row instanceof Record ) {
			$this->updateById( $row->id, [
				'last_export_request_at' => Services::Request()->ts(),
			] );
		}
	}

	public function recordExportSuccess( string $url, string $resultCode, string $importID = '' ) :void {
		$row = $this->findByUrl( $url );
		if ( !$row instanceof Record ) {
			return;
		}

		$now = Services::Request()->ts();
		$data = [
			'queue_status'             => SitesDB::QUEUE_IDLE,
			'last_export_request_at'   => $now,
			'last_export_success_at'   => $now,
			'last_export_result_code'  => $resultCode,
			'last_export_error'        => '',
			'consecutive_failures'     => 0,
			'next_ping_at'             => $now + \DAY_IN_SECONDS,
			'expected_export_by'       => 0,
			'lock_until'               => 0,
			'picked_at'                => 0,
		];
		if ( !empty( $importID ) ) {
			$data[ 'import_id' ] = $importID;
		}
		$this->updateById( $row->id, $data );
	}

	public function recordExportFailure( string $url, string $resultCode, string $error ) :void {
		$row = $this->findByUrl( $url );
		if ( !$row instanceof Record ) {
			return;
		}

		$failures = $row->consecutive_failures + 1;
		$this->updateById( $row->id, [
			'queue_status'             => SitesDB::QUEUE_QUEUED,
			'last_export_request_at'   => Services::Request()->ts(),
			'last_export_failure_at'   => Services::Request()->ts(),
			'last_export_result_code'  => $resultCode,
			'last_export_error'        => $this->trimError( $error ),
			'consecutive_failures'     => $failures,
			'next_ping_at'             => $this->nextRetryAt( $failures ),
			'expected_export_by'       => 0,
			'lock_until'               => 0,
			'picked_at'                => 0,
		] );
	}

	public function nextRetryAt( int $consecutiveFailures ) :int {
		$failurePower = \max( 0, $consecutiveFailures - 1 );
		$delay = \min( \DAY_IN_SECONDS, 15*\MINUTE_IN_SECONDS*( 2**\min( $failurePower, 8 ) ) );
		return Services::Request()->ts() + $delay;
	}

	/**
	 * @return Record[]
	 */
	public function selectActiveRows() :array {
		return $this->db()
					->getQuerySelector()
					->setNoOrderBy()
					->setOrderBy( 'id', 'ASC' )
					->addWhereEquals( 'status', SitesDB::STATUS_ACTIVE )
					->queryWithResult() ?? [];
	}

	public function countAllRows() :int {
		return $this->countRowsWithSql();
	}

	public function countActiveRows() :int {
		$dbh = $this->dbOrNull();
		if ( !( $dbh instanceof SitesDB ) || !$dbh->isReady() ) {
			return 0;
		}

		return $this->countRowsWithSql( $this->buildFilteredWhere( '', [
			sprintf( "`status`='%s' AND `deleted_at`=0", SitesDB::STATUS_ACTIVE ),
		] ) );
	}

	public function countFilteredRows( string $search = '', array $wheres = [] ) :int {
		return $this->countRowsWithSql( $this->buildFilteredWhere( $search, $wheres ) );
	}

	/**
	 * @return Record[]
	 */
	public function selectFilteredRows(
		string $search,
		int $offset,
		int $limit,
		string $orderBy,
		string $orderDir,
		array $wheres = []
	) :array {
		$allowedOrder = \array_flip( $this->db()->getTableSchema()->getColumnNames() );
		$orderBy = isset( $allowedOrder[ $orderBy ] ) ? $orderBy : 'updated_at';
		$orderDir = \strtoupper( $orderDir ) === 'ASC' ? 'ASC' : 'DESC';

		return $this->selectFilteredRowsWithSql(
			$this->buildFilteredWhere( $search, $wheres ),
			\max( 0, $offset ),
			\max( 1, $limit ),
			$orderBy,
			$orderDir
		);
	}

	public function findByUrl( string $url, bool $includeDeleted = false ) :?Record {
		$url = $this->canonicalizeUrl( $url );
		if ( empty( $url ) ) {
			return null;
		}
		return $this->findByUrls( [ $url ], $includeDeleted )[ $url ] ?? null;
	}

	/**
	 * @return array<string,Record>
	 */
	public function findByUrls( array $urls, bool $includeDeleted = false ) :array {
		$dbh = $this->dbOrNull();
		if ( !( $dbh instanceof SitesDB ) || !$dbh->isReady() ) {
			return [];
		}

		$urls = $this->canonicalUrls( $urls );
		if ( empty( $urls ) ) {
			return [];
		}

		$hashToUrl = [];
		foreach ( $urls as $url ) {
			$hashToUrl[ \hash( 'md5', $url ) ] = $url;
		}

		$results = [];
		foreach ( $this->selectRowsByHashes( \array_keys( $hashToUrl ), $includeDeleted ) as $hash => $row ) {
			if ( isset( $hashToUrl[ $hash ] ) ) {
				$results[ $hashToUrl[ $hash ] ] = $row;
			}
		}

		return $results;
	}

	public function findById( int $id, bool $includeDeleted = false ) :?Record {
		$dbh = $this->dbOrNull();
		if ( !( $dbh instanceof SitesDB ) || !$dbh->isReady() ) {
			return null;
		}
		return $dbh
					->getQuerySelector()
					->setIncludeSoftDeleted( $includeDeleted )
					->addWhereEquals( 'id', $id )
					->first();
	}

	/**
	 * @return Record[]
	 */
	private function findActiveByIds( array $ids ) :array {
		$ids = $this->sanitiseIds( $ids );
		if ( empty( $ids ) ) {
			return [];
		}

		$rows = [];
		foreach ( \array_chunk( $ids, self::SQL_BATCH_SIZE ) as $chunk ) {
			$rows = \array_merge( $rows, $this->db()
										   ->getQuerySelector()
										   ->addWhereEquals( 'status', SitesDB::STATUS_ACTIVE )
										   ->addWhereIn( 'id', $chunk )
										   ->queryWithResult() ?? [] );
		}

		return $rows;
	}

	private function sanitiseIds( array $ids ) :array {
		return \array_values( \array_unique( \array_filter( \array_map( '\intval', $ids ), static fn( int $id ) :bool => $id > 0 ) ) );
	}

	private function storeOptionsIfChanged() :void {
		if ( self::con()->opts->hasChanges() ) {
			self::con()->opts->store();
		}
	}

	private function canonicalLegacyWhitelistUrls() :array {
		$raw = self::con()->opts->optGet( 'importexport_whitelist' );
		return $this->canonicalUrls( \is_array( $raw ) ? $raw : [] );
	}

	private function canonicalUrls( array $urls ) :array {
		return \array_values( \array_unique( \array_filter( \array_map(
			fn( $url ) :string => $this->canonicalizeUrl( (string)$url ),
			$urls
		) ) ) );
	}

	private function legacyImportIds() :array {
		$ids = self::con()->opts->optGet( 'import_url_ids' );
		return \is_array( $ids ) ? $ids : [];
	}

	private function canonicalOldQueueUrls( array $fallbackUrls ) :array {
		if ( empty( $fallbackUrls ) ) {
			return [];
		}

		$queued = [];
		try {
			$queue = new WhitelistNotifyQueue( self::OLD_QUEUE_ACTION, self::con()->prefix() );
			foreach ( $queue->get_batches() as $batch ) {
				foreach ( \is_array( $batch->data ?? null ) ? $batch->data : [] as $url ) {
					$url = $this->canonicalizeUrl( (string)$url );
					if ( !empty( $url ) && \in_array( $url, $fallbackUrls, true ) ) {
						$queued[] = $url;
					}
				}
			}
		}
		catch ( \Throwable $e ) {
		}

		return \array_values( \array_unique( $queued ) );
	}

	private function clearOldQueueState() :void {
		try {
			( new WhitelistNotifyQueue( self::OLD_QUEUE_ACTION, self::con()->prefix() ) )->delete_all();
		}
		catch ( \Throwable $e ) {
		}

		if ( \function_exists( 'wp_clear_scheduled_hook' ) ) {
			\wp_clear_scheduled_hook( self::con()->prefix( self::OLD_NOTIFY_CRON ) );
			\wp_clear_scheduled_hook( self::con()->prefix().'_'.self::OLD_QUEUE_ACTION.'_cron' );
			\wp_clear_scheduled_hook( self::con()->prefix().'_'.self::OLD_QUEUE_ACTION.'_expired_cron' );
		}
	}

	private function buildQueueDueData( int $now ) :array {
		return [
			'queue_status'       => SitesDB::QUEUE_QUEUED,
			'queued_at'          => $now,
			'next_ping_at'       => $now,
			'picked_at'          => 0,
			'lock_until'         => 0,
			'expected_export_by' => 0,
		];
	}

	private function buildPendingInviteDueData( int $now ) :array {
		return [
			'queue_status'       => SitesDB::QUEUE_PENDING_INVITE,
			'queued_at'          => $now,
			'next_ping_at'       => $now,
			'picked_at'          => 0,
			'lock_until'         => 0,
			'expected_export_by' => 0,
		];
	}

	private function buildActiveUpsertData(
		?Record $row,
		string $url,
		string $source,
		string $importID,
		bool $markDue,
		int $now
	) :array {
		$data = [
			'url'        => $url,
			'url_hash'   => \hash( 'md5', $url ),
			'status'     => SitesDB::STATUS_ACTIVE,
			'deleted_at' => 0,
		];

		if ( !empty( $source ) && ( !$row instanceof Record || empty( $row->source ) ) ) {
			$data[ 'source' ] = $source;
		}
		if ( !empty( $importID ) ) {
			$data[ 'import_id' ] = $importID;
		}
		if ( !$row instanceof Record || $markDue || ( $row->next_ping_at <= 0 && $row->queue_status !== SitesDB::QUEUE_WAITING_EXPORT ) ) {
			$data = \array_merge( $data, $this->buildQueueDueData( $now ) );
		}

		return $data;
	}

	private function buildPendingClientSiteUpsertData(
		?Record $row,
		string $url,
		string $source,
		bool $sendInvite,
		int $now
	) :array {
		$queueStatus = $sendInvite ? SitesDB::QUEUE_PENDING_INVITE : SitesDB::QUEUE_PENDING_CONNECTION;
		$data = [
			'url'                  => $url,
			'url_hash'             => \hash( 'md5', $url ),
			'status'               => SitesDB::STATUS_ACTIVE,
			'queue_status'         => $queueStatus,
			'deleted_at'           => 0,
			'queued_at'            => $now,
			'picked_at'            => 0,
			'lock_until'           => 0,
			'next_ping_at'         => $sendInvite ? $now : 0,
			'expected_export_by'   => 0,
			'consecutive_failures' => 0,
		];

		if ( !empty( $source ) && ( !$row instanceof Record || empty( $row->source ) ) ) {
			$data[ 'source' ] = $source;
		}

		return $data;
	}

	private function buildActiveInsertData(
		string $url,
		string $source,
		string $importID,
		bool $markDue,
		int $now
	) :array {
		return \array_merge( [
			'import_id'               => '',
			'source'                  => $source,
			'queue_status'            => SitesDB::QUEUE_QUEUED,
			'priority'                => 0,
			'queued_at'               => $now,
			'picked_at'               => 0,
			'lock_until'              => 0,
			'next_ping_at'            => $now,
			'expected_export_by'      => 0,
			'last_ping_attempt_at'    => 0,
			'last_ping_success_at'    => 0,
			'last_ping_failure_at'    => 0,
			'last_ping_http_code'     => 0,
			'last_ping_error'         => '',
			'last_export_request_at'  => 0,
			'last_export_success_at'  => 0,
			'last_export_failure_at'  => 0,
			'last_export_result_code' => '',
			'last_export_error'       => '',
			'ping_attempts_total'     => 0,
			'consecutive_failures'    => 0,
			'meta'                    => [],
			'created_at'              => $now,
			'updated_at'              => $now,
		], $this->buildActiveUpsertData( null, $url, $source, $importID, $markDue, $now ) );
	}

	private function buildPendingClientSiteInsertData(
		string $url,
		string $source,
		bool $sendInvite,
		int $now
	) :array {
		return \array_merge(
			$this->buildActiveInsertData( $url, $source, '', false, $now ),
			$this->buildPendingClientSiteUpsertData( null, $url, $source, $sendInvite, $now )
		);
	}

	private function queueRows( array $rows ) :int {
		if ( empty( $rows ) ) {
			return 0;
		}

		$syncRows = [];
		$inviteRows = [];
		foreach ( $rows as $row ) {
			if ( $row->queue_status === SitesDB::QUEUE_PENDING_INVITE ) {
				$inviteRows[] = $row;
			}
			elseif ( $row->queue_status === SitesDB::QUEUE_PENDING_CONNECTION ) {
				continue;
			}
			else {
				$syncRows[] = $row;
			}
		}

		$now = Services::Request()->ts();
		$this->bulkUpdateRowsByIds(
			\array_map( static fn( Record $row ) :int => $row->id, $syncRows ),
			$this->buildQueueDueData( $now )
		);
		$this->bulkUpdateRowsByIds(
			\array_map( static fn( Record $row ) :int => $row->id, $inviteRows ),
			$this->buildPendingInviteDueData( $now )
		);

		return \count( $syncRows ) + \count( $inviteRows );
	}

	private function bulkInsertRows( array $rows ) :bool {
		if ( empty( $rows ) ) {
			return true;
		}

		$success = true;
		foreach ( \array_chunk( $rows, self::SQL_BATCH_SIZE ) as $chunk ) {
			$first = \reset( $chunk );
			if ( !\is_array( $first ) ) {
				continue;
			}
			$columns = \array_keys( $first );
			$valueRows = [];
			$values = [];

			foreach ( $chunk as $row ) {
				$placeholders = [];
				foreach ( $columns as $column ) {
					$value = $this->normaliseSqlValue( $row[ $column ] ?? '' );
					$placeholders[] = $this->sqlPlaceholder( $value );
					$values[] = $value;
				}
				$valueRows[] = '('.\implode( ',', $placeholders ).')';
			}

			$success = $this->executePreparedSql(
				sprintf(
					'INSERT IGNORE INTO `%s` (`%s`) VALUES %s;',
					$this->db()->getTable(),
					\implode( '`,`', \array_map( [ $this, 'sqlColumnName' ], $columns ) ),
					\implode( ',', $valueRows )
				),
				$values
			) && $success;
		}

		return $success;
	}

	private function bulkUpdateRowsByHash( array $rowsByHash ) :bool {
		if ( empty( $rowsByHash ) ) {
			return true;
		}

		$success = true;
		foreach ( \array_chunk( $rowsByHash, self::SQL_BATCH_SIZE, true ) as $chunk ) {
			$columns = [];
			foreach ( $chunk as $data ) {
				$columns = \array_unique( \array_merge( $columns, \array_keys( $data ) ) );
			}

			$sets = [];
			$values = [];
			foreach ( $columns as $column ) {
				$cases = [];
				foreach ( $chunk as $hash => $data ) {
					if ( \array_key_exists( $column, $data ) ) {
						$value = $this->normaliseSqlValue( $data[ $column ] );
						$cases[] = sprintf( 'WHEN %%s THEN %s', $this->sqlPlaceholder( $value ) );
						$values[] = (string)$hash;
						$values[] = $value;
					}
				}
				if ( !empty( $cases ) ) {
					$column = $this->sqlColumnName( $column );
					$sets[] = sprintf( '`%s`=CASE `url_hash` %s ELSE `%s` END', $column, \implode( ' ', $cases ), $column );
				}
			}

			if ( empty( $sets ) ) {
				continue;
			}

			$hashes = \array_keys( $chunk );
			$success = $this->executePreparedSql(
				sprintf(
					'UPDATE `%s` SET %s WHERE `url_hash` IN (%s);',
					$this->db()->getTable(),
					\implode( ',', $sets ),
					$this->sqlPlaceholders( $hashes )
				),
				\array_merge( $values, $hashes )
			) && $success;
		}

		return $success;
	}

	private function bulkUpdateRowsByIds( array $ids, array $data ) :bool {
		$ids = \array_values( \array_unique( \array_filter( \array_map( '\intval', $ids ), static fn( int $id ) :bool => $id > 0 ) ) );
		if ( empty( $ids ) || empty( $data ) ) {
			return true;
		}

		$data = $this->withUpdatedAt( $data, Services::Request()->ts() );
		$sets = [];
		$values = [];
		foreach ( $data as $column => $value ) {
			$value = $this->normaliseSqlValue( $value );
			$sets[] = sprintf( '`%s`=%s', $this->sqlColumnName( $column ), $this->sqlPlaceholder( $value ) );
			$values[] = $value;
		}

		$success = true;
		foreach ( \array_chunk( $ids, self::SQL_BATCH_SIZE ) as $chunk ) {
			$success = $this->executePreparedSql(
				sprintf(
					'UPDATE `%s` SET %s WHERE `id` IN (%s);',
					$this->db()->getTable(),
					\implode( ',', $sets ),
					$this->sqlPlaceholders( $chunk, '%d' )
				),
				\array_merge( $values, $chunk )
			) && $success;
		}

		return $success;
	}

	/**
	 * @return Record[]
	 */
	private function selectDueRowsForClaim( int $now, int $limit ) :array {
		return $this->selectRowsWithSql( $this->prepareSql(
			sprintf(
				"SELECT * FROM `%s`
				 WHERE `deleted_at`=0
				   AND `status`=%%s
				   AND `queue_status` IN (%%s,%%s)
				   AND `next_ping_at`<=%%d
				   AND (`lock_until`=0 OR `lock_until`<=%%d)
				 ORDER BY `priority` DESC, `next_ping_at` ASC, `id` ASC
				 LIMIT %%d",
				$this->db()->getTable()
			),
			[
				SitesDB::STATUS_ACTIVE,
				SitesDB::QUEUE_IDLE,
				SitesDB::QUEUE_QUEUED,
				(int)$now,
				(int)$now,
				\max( 1, (int)$limit ),
			]
		) );
	}

	/**
	 * @return Record[]
	 */
	private function selectDueInviteRowsForClaim( int $now, int $limit ) :array {
		return $this->selectRowsWithSql( $this->prepareSql(
			sprintf(
				"SELECT * FROM `%s`
				 WHERE `deleted_at`=0
				   AND `status`=%%s
				   AND `queue_status`=%%s
				   AND `next_ping_at`>0
				   AND `next_ping_at`<=%%d
				   AND (`lock_until`=0 OR `lock_until`<=%%d)
				 ORDER BY `priority` DESC, `next_ping_at` ASC, `id` ASC
				 LIMIT %%d",
				$this->db()->getTable()
			),
			[
				SitesDB::STATUS_ACTIVE,
				SitesDB::QUEUE_PENDING_INVITE,
				(int)$now,
				(int)$now,
				\max( 1, (int)$limit ),
			]
		) );
	}

	/**
	 * @return Record[]
	 */
	private function selectExpiredProcessingRowsForRecovery( int $now, int $limit ) :array {
		return $this->selectRowsWithSql( $this->prepareSql(
			sprintf(
				"SELECT * FROM `%s`
				 WHERE `deleted_at`=0
				   AND `status`=%%s
				   AND `queue_status`=%%s
				   AND `lock_until`>0
				   AND `lock_until`<=%%d
				 ORDER BY `lock_until` ASC, `id` ASC
				 LIMIT %%d",
				$this->db()->getTable()
			),
			[
				SitesDB::STATUS_ACTIVE,
				SitesDB::QUEUE_PROCESSING,
				(int)$now,
				\max( 1, (int)$limit ),
			]
		) );
	}

	/**
	 * @return Record[]
	 */
	private function selectExpiredWaitingExportRowsWithSql( int $now, int $limit ) :array {
		return $this->selectRowsWithSql( $this->prepareSql(
			sprintf(
				"SELECT * FROM `%s`
				 WHERE `deleted_at`=0
				   AND `status`=%%s
				   AND `queue_status`=%%s
				   AND `expected_export_by`>0
				   AND `expected_export_by`<=%%d
				   AND (`last_export_success_at`=0 OR `last_export_success_at`<`last_ping_success_at`)
				 ORDER BY `expected_export_by` ASC, `id` ASC
				 LIMIT %%d",
				$this->db()->getTable()
			),
			[
				SitesDB::STATUS_ACTIVE,
				SitesDB::QUEUE_WAITING_EXPORT,
				(int)$now,
				\max( 1, (int)$limit ),
			]
		) );
	}

	private function countRowsWithSql( string $where = '' ) :int {
		return (int)Services::WpDb()->getVar( sprintf(
			'SELECT COUNT(*) FROM `%s` %s',
			$this->db()->getTable(),
			$where
		) );
	}

	/**
	 * @return Record[]
	 */
	private function selectFilteredRowsWithSql(
		string $where,
		int $offset,
		int $limit,
		string $orderBy,
		string $orderDir
	) :array {
		return $this->selectRowsWithSql( sprintf(
			"SELECT * FROM `%s` %s ORDER BY `%s` %s, `id` DESC LIMIT %d OFFSET %d",
			$this->db()->getTable(),
			$where,
			$orderBy,
			$orderDir,
			\max( 1, $limit ),
			\max( 0, $offset )
		) );
	}

	/**
	 * @return array<string,Record>
	 */
	private function selectRowsByHashes( array $hashes, bool $includeDeleted = false ) :array {
		$hashes = \array_values( \array_unique( \array_filter( \array_map( 'strval', $hashes ) ) ) );
		if ( empty( $hashes ) ) {
			return [];
		}

		$rowsByHash = [];
		foreach ( \array_chunk( $hashes, self::SQL_BATCH_SIZE ) as $chunk ) {
			$where = sprintf( '`url_hash` IN (%s)', $this->sqlPlaceholders( $chunk ) );
			if ( !$includeDeleted ) {
				$where .= ' AND `deleted_at`=0';
			}

			foreach ( $this->selectRowsWithSql( $this->prepareSql(
				sprintf(
					'SELECT * FROM `%s` WHERE %s;',
					$this->db()->getTable(),
					$where
				),
				$chunk
			) ) as $row ) {
				$rowsByHash[ $row->url_hash ] = $row;
			}
		}

		return $rowsByHash;
	}

	private function rowNeedsUpdate( Record $row, array $data ) :bool {
		foreach ( $data as $key => $value ) {
			$current = $row->{$key};
			if ( \is_int( $current ) ) {
				if ( $current !== (int)$value ) {
					return true;
				}
			}
			elseif ( \is_array( $current ) || \is_array( $value ) ) {
				if ( $current !== $value ) {
					return true;
				}
			}
			elseif ( (string)$current !== (string)$value ) {
				return true;
			}
		}

		return false;
	}

	private function withUpdatedAt( array $data, int $now ) :array {
		if ( !isset( $data[ 'updated_at' ] ) && $this->db()->getTableSchema()->has_updated_at ) {
			$data[ 'updated_at' ] = $now;
		}
		return $data;
	}

	private function normaliseSqlValue( $value ) {
		if ( \is_array( $value ) ) {
			$value = $this->db()->getRecord()->arrayDataWrap( $value ) ?? '';
		}
		elseif ( \is_bool( $value ) ) {
			$value = (int)$value;
		}
		elseif ( $value === null ) {
			$value = '';
		}
		return $value;
	}

	private function sqlPlaceholder( $value ) :string {
		return \is_int( $value ) ? '%d' : '%s';
	}

	private function sqlPlaceholders( array $values, string $placeholder = '%s' ) :string {
		return \implode( ',', \array_fill( 0, \count( $values ), $placeholder ) );
	}

	private function sqlColumnName( string $column ) :string {
		if ( !\in_array( $column, $this->db()->getTableSchema()->getColumnNames(), true ) ) {
			throw new \InvalidArgumentException( 'Invalid import/export sites column.' );
		}

		return $column;
	}

	private function prepareSql( string $sql, array $values ) :string {
		if ( empty( $values ) ) {
			return $sql;
		}

		global $wpdb;
		return (string)$wpdb->prepare( $sql, ...$values );
	}

	private function executePreparedSql( string $sql, array $values ) :bool {
		return Services::WpDb()->doSql( $this->prepareSql( $sql, $values ) ) !== false;
	}

	private function updateById( int $id, array $data ) :bool {
		$dbh = $this->dbOrNull();
		if ( !( $dbh instanceof SitesDB ) || !$dbh->isReady() ) {
			return false;
		}
		if ( isset( $data[ 'meta' ] ) && \is_array( $data[ 'meta' ] ) ) {
			$data[ 'meta' ] = $dbh->getRecord()->arrayDataWrap( $data[ 'meta' ] ) ?? '';
		}
		return $dbh
					->getQueryUpdater()
					->updateById( $id, $data );
	}

	private function buildFilteredWhere( string $search, array $wheres = [] ) :string {
		$clauses = \array_values( \array_filter( \array_map(
			static fn( $where ) :string => \trim( (string)$where ),
			$wheres
		) ) );

		$searchClause = $this->buildSearchWhereClause( $search );
		if ( !empty( $searchClause ) ) {
			$clauses[] = $searchClause;
		}

		return empty( $clauses ) ? '' : 'WHERE '.\implode( ' AND ', \array_map(
			static fn( string $clause ) :string => \sprintf( '(%s)', $clause ),
			$clauses
		) );
	}

	private function buildSearchWhereClause( string $search ) :string {
		$search = \trim( $search );
		if ( empty( $search ) ) {
			return '';
		}

		global $wpdb;
		$like = '%'.$wpdb->esc_like( $search ).'%';
		return $this->prepareSql(
			'`url` LIKE %s OR `status` LIKE %s OR `queue_status` LIKE %s OR `last_ping_error` LIKE %s OR `last_export_error` LIKE %s',
			\array_fill( 0, 5, $like )
		);
	}

	/**
	 * @return Record[]
	 */
	private function selectRowsWithSql( string $sql ) :array {
		$rows = Services::WpDb()->selectCustom( $sql );
		if ( !\is_array( $rows ) ) {
			return [];
		}

		return \array_map( function ( array $row ) :Record {
			return $this->db()->getRecord()->applyFromArray( $row );
		}, $rows );
	}

	private function trimError( string $error ) :string {
		return \substr( \trim( $error ), 0, 1000 );
	}

	private function db() :SitesDB {
		return self::con()->db_con->import_export_sites;
	}

	private function dbOrNull() :?SitesDB {
		try {
			return $this->db();
		}
		catch ( \Throwable $e ) {
			return null;
		}
	}

	private function hasConfigHandler() :bool {
		try {
			$opts = self::con()->opts;
			return \is_object( $opts )
				   && \method_exists( $opts, 'optGet' )
				   && \method_exists( $opts, 'optSet' )
				   && \method_exists( $opts, 'hasChanges' )
				   && \method_exists( $opts, 'store' );
		}
		catch ( \Throwable $e ) {
			return false;
		}
	}
}
