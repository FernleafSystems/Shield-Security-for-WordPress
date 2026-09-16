<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Sites;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ImportExportProfiles\Ops\Record as ProfileRecord;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ImportExportSites\Ops\{
	Handler as SitesDB,
	Record
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Profiles\ProfileRepository;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Diagnostics\SyncObservation;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\WhitelistNotifyQueue;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class SiteRepository {

	use PluginControllerConsumer;

	public const MIGRATED_AT_OPTION = 'importexport_sites_migrated_at';
	public const OLD_NOTIFY_CRON = 'importexport_notify';
	public const OLD_QUEUE_ACTION = 'whitelist_notify_urls';
	private const META_EXPORT_SERVED_AT = 'export_served_at';
	private const META_HANDSHAKE_ATTEMPT_AT = 'handshake_attempt_at';
	private const META_SYNC_OBSERVATIONS = 'sync_observations';
	private const OBSERVATION_SLOTS = [
		SyncObservation::PHASE_NOTIFICATION,
		SyncObservation::PHASE_VERIFICATION,
		SyncObservation::PHASE_EXPORT,
	];
	private const META_WRITE_ATTEMPTS = 3;
	private const SQL_BATCH_SIZE = 20;
	private ?int $defaultProfileRef = null;

	public function ensureLegacyImported( bool $includeOldQueueState = true ) :bool {
		$dbh = $this->dbOrNull();
		if ( !( $dbh instanceof SitesDB ) || !$dbh->isReady() ) {
			return false;
		}
		if ( !$this->hasConfigHandler() ) {
			return true;
		}
		if ( (int)self::con()->opts->optGet( self::MIGRATED_AT_OPTION ) > 0 ) {
			return true;
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
			$importID = (string)( $urlIds[ $this->urlHash( $url ) ] ?? '' );
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

		$insertSucceeded = $this->bulkInsertRows( $insertRows );
		$updateSucceeded = $this->bulkUpdateRowsByHash( $updateRowsByHash );
		if ( !$insertSucceeded || !$updateSucceeded ) {
			return false;
		}

		self::con()->opts->optSet( self::MIGRATED_AT_OPTION, $now );
		$this->storeOptionsIfChanged();
		$this->clearOldQueueState();
		return true;
	}

	public function canonicalizeUrl( string $url ) :string {
		return ( new SyncSiteUrlValidator() )->canonicalize( $url );
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
		if ( $row instanceof Record && $row->status === SitesDB::STATUS_ACTIVE && $row->deleted_at === 0 ) {
			return $row;
		}
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

	public function repairConnectionsByIds( array $ids ) :int {
		$rows = $this->findActiveByIds( $ids );
		if ( empty( $rows ) ) {
			return 0;
		}

		$now = Services::Request()->ts();
		$count = 0;
		foreach ( $rows as $row ) {
			if ( !$this->isRepairableConnectionRow( $row, $now ) ) {
				continue;
			}

			if ( $this->updateById( $row->id, \array_merge(
				$this->buildQueueDueData( $now ),
				$this->buildConnectionResetData( $row )
			) ) ) {
				$count++;
			}
		}

		return $count;
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

	public function selectNextInterruptedNotification( ?int $now = null ) :?Record {
		$rows = $this->selectExpiredProcessingRowsForRecovery( $now ?? Services::Request()->ts(), 1 );
		return $rows[ 0 ] ?? null;
	}

	public function refreshInterruptedNotification( Record $selected, ?int $now = null ) :?Record {
		$current = $this->findById( $selected->id, true );
		$now = $now ?? Services::Request()->ts();
		return $current instanceof Record
			   && $current->status === SitesDB::STATUS_ACTIVE
			   && $current->deleted_at === 0
			   && $current->queue_status === SitesDB::QUEUE_PROCESSING
			   && $current->lock_until > 0
			   && $current->lock_until <= $now
			? $current
			: null;
	}

	public function selectNextDueWork( ?int $now = null ) :?Record {
		$rows = $this->selectDueWork( $now ?? Services::Request()->ts(), 1 );
		return $rows[ 0 ] ?? null;
	}

	public function hasActionableWork( ?int $now = null ) :bool {
		$now = $now ?? Services::Request()->ts();
		return $this->selectNextInterruptedNotification( $now ) instanceof Record
			   || $this->selectNextDueWork( $now ) instanceof Record
			   || !empty( $this->selectExpiredWaitingExportRowsWithSql( $now, 1 ) )
			   || !empty( $this->selectReconcilableWaitingExportRowsWithSql( 1 ) );
	}

	/**
	 * @return Record[]
	 */
	public function selectExportMaintenanceRows( int $limit ) :array {
		$limit = \max( 0, $limit );
		if ( $limit === 0 ) {
			return [];
		}

		$rows = $this->selectExpiredWaitingExportRowsWithSql( Services::Request()->ts(), $limit );
		$remaining = $limit - \count( $rows );
		return $remaining > 0
			? \array_merge( $rows, $this->selectReconcilableWaitingExportRowsWithSql( $remaining ) )
			: $rows;
	}

	/**
	 * @return false|int
	 */
	public function startInviteAttempt( Record $row, int $startedAt ) {
		$invitation = ( new InvitationMetadata() )->normalize( $row->meta );
		if ( $invitation[ 'attempts_started' ] >= InvitationMetadata::MAX_ATTEMPTS ) {
			return 0;
		}
		$attempt = $invitation[ 'attempts_started' ] + 1;
		$deadline = $startedAt + QueueProcessor::INVITE_PROCESSING_DEADLINE;
		$nextPingAt = $attempt === InvitationMetadata::MAX_ATTEMPTS
			? $deadline
			: $startedAt + ( $attempt === 1 ? 15 : 30 )*\MINUTE_IN_SECONDS;

		$data = [
			'meta'         => ( new InvitationMetadata() )->startAttempt( $row->meta, $startedAt ),
			'next_ping_at' => $nextPingAt,
			'picked_at'    => $startedAt,
			'lock_until'   => $deadline,
		];
		$result = $this->conditionalMetadataUpdate( $row, $data );
		if ( $result === 1 ) {
			foreach ( $data as $key => $value ) {
				$row->{$key} = $value;
			}
		}
		return $result;
	}

	/**
	 * @return false|int
	 */
	public function recordInviteResult( Record $row, string $result, int $httpStatus = 0 ) {
		$invitation = ( new InvitationMetadata() )->normalize( $row->meta );
		if ( $invitation[ 'attempts_started' ] < 1 || $invitation[ 'last_result' ] !== InvitationMetadata::RESULT_STARTED ) {
			return 0;
		}
		$isComplete = $result === InvitationMetadata::RESULT_HTTP_RESPONSE
					  || $invitation[ 'attempts_started' ] >= InvitationMetadata::MAX_ATTEMPTS;
		$data = [
			'meta'       => ( new InvitationMetadata() )->withResult( $row->meta, $result, $httpStatus ),
			'lock_until' => 0,
			'picked_at'  => 0,
		];
		if ( $isComplete ) {
			$data[ 'queue_status' ] = SitesDB::QUEUE_PENDING_CONNECTION;
			$data[ 'next_ping_at' ] = 0;
		}

		return $this->conditionalMetadataUpdate( $row, $data, true );
	}

	/**
	 * @return false|int
	 */
	public function settleInterruptedFinalInviteAttempt( Record $row ) {
		$invitation = ( new InvitationMetadata() )->normalize( $row->meta );
		if ( $invitation[ 'attempts_started' ] !== InvitationMetadata::MAX_ATTEMPTS
			 || $invitation[ 'last_result' ] !== InvitationMetadata::RESULT_STARTED ) {
			return 0;
		}

		return $this->conditionalMetadataUpdate( $row, [
			'queue_status' => SitesDB::QUEUE_PENDING_CONNECTION,
			'next_ping_at' => 0,
			'lock_until'   => 0,
			'picked_at'    => 0,
		], true );
	}

	public function restartInvitationsByIds( array $ids ) :array {
		$counts = [
			'queued_count'  => 0,
			'skipped_count' => 0,
			'failed_count'  => 0,
		];
		$now = Services::Request()->ts();
		foreach ( $this->sanitiseIds( $ids ) as $id ) {
			$row = $this->findById( $id, true );
			if ( !$row instanceof Record || $row->status !== SitesDB::STATUS_ACTIVE
				 || $row->deleted_at > 0 || $row->queue_status !== SitesDB::QUEUE_PENDING_CONNECTION ) {
				$counts[ 'skipped_count' ]++;
				continue;
			}

			$result = $this->conditionalMetadataUpdate( $row, [
				'queue_status' => SitesDB::QUEUE_PENDING_INVITE,
				'queued_at'    => $now,
				'next_ping_at' => $now,
				'picked_at'    => 0,
				'lock_until'   => 0,
				'meta'         => ( new InvitationMetadata() )->replace( $row->meta, ( new InvitationMetadata() )->newCycle() ),
			], false, SitesDB::QUEUE_PENDING_CONNECTION );
			if ( $result === false ) {
				$counts[ 'failed_count' ]++;
			}
			elseif ( $result === 1 ) {
				$counts[ 'queued_count' ]++;
			}
			else {
				$counts[ 'skipped_count' ]++;
			}
		}
		return $counts;
	}

	public function startNotificationAttempt( Record $row, int $startedAt, bool $recovery = false ) :bool {
		$metadata = new NotificationMetadata();
		$meta = $recovery ? $metadata->increment( $row->meta ) : $metadata->startFreshCycle( $row->meta );
		$data = [
			'queue_status'        => SitesDB::QUEUE_PROCESSING,
			'picked_at'           => $startedAt,
			'lock_until'          => $startedAt + \MINUTE_IN_SECONDS,
			'last_ping_attempt_at' => $startedAt,
			'ping_attempts_total'  => $row->ping_attempts_total + 1,
			'meta'                 => $meta,
		];
		$result = $this->updateById( $row->id, $data );
		if ( $result ) {
			foreach ( $data as $key => $value ) {
				$row->{$key} = $value;
			}
		}
		return $result;
	}

	/**
	 * @return false|int
	 */
	public function recordNotifyDispatched( Record $row, int $httpCode, int $expectedExportBy ) {
		$now = Services::Request()->ts();
		return $this->recordNotificationResult( $row, static function ( Record $current ) use ( $httpCode, $expectedExportBy, $now ) :array {
			return [
				'queue_status'         => SitesDB::QUEUE_WAITING_EXPORT,
				'last_ping_success_at' => $now,
				'last_ping_http_code'  => $httpCode,
				'last_ping_error'      => '',
				'expected_export_by'   => $expectedExportBy,
				'lock_until'           => 0,
				'picked_at'            => 0,
				'meta'                 => ( new NotificationMetadata() )->reset( $current->meta ),
			];
		} );
	}

	public function exportCooldownActive( Record $row, int $cooldown ) :bool {
		if ( ExportWaitState::isCooldownBypassed( $row, Services::Request()->ts() ) ) {
			return false;
		}
		return $this->metaTimestampWithinCooldown( $row, self::META_EXPORT_SERVED_AT, $cooldown );
	}

	public function recordExportServed( Record $row ) :void {
		$this->setMetaTimestamp( $row, self::META_EXPORT_SERVED_AT );
	}

	public function readObservation( Record $row, string $slot ) :?array {
		if ( !\in_array( $slot, self::OBSERVATION_SLOTS, true ) ) {
			return null;
		}
		$root = \is_array( $row->meta ) ? ( $row->meta[ self::META_SYNC_OBSERVATIONS ] ?? null ) : null;
		$observation = \is_array( $root ) ? SyncObservation::normalize( $root[ $slot ] ?? null ) : null;
		return $observation !== null && $observation[ 'phase' ] === $slot ? $observation : null;
	}

	public function saveObservation( Record $row, string $slot, array $observation ) :bool {
		$observation = SyncObservation::normalize( $observation );
		if ( $observation === null
			 || !\in_array( $slot, self::OBSERVATION_SLOTS, true )
			 || $observation[ 'phase' ] !== $slot ) {
			return false;
		}

		try {
			$current = $this->findById( $row->id, true );
			for ( $attempt = 0; $attempt < self::META_WRITE_ATTEMPTS; $attempt++ ) {
				if ( !$current instanceof Record
					 || $current->status !== SitesDB::STATUS_ACTIVE
					 || $current->deleted_at !== 0 ) {
					return false;
				}

				$meta = \is_array( $current->meta ) ? $current->meta : [];
				$root = \is_array( $meta[ self::META_SYNC_OBSERVATIONS ] ?? null )
					? $meta[ self::META_SYNC_OBSERVATIONS ]
					: [];
				$root[ $slot ] = $observation;
				$meta[ self::META_SYNC_OBSERVATIONS ] = $root;
				$result = $this->conditionalMetadataUpdate( $current, [ 'meta' => $meta ], false, null );
				if ( $result === 1 ) {
					return true;
				}
				if ( $result === false ) {
					return false;
				}
				$current = $this->findById( $row->id, true );
			}
		}
		catch ( \Throwable $e ) {
		}
		return false;
	}

	public function handshakeCooldownActive( Record $row, int $cooldown ) :bool {
		return $this->metaTimestampWithinCooldown( $row, self::META_HANDSHAKE_ATTEMPT_AT, $cooldown );
	}

	public function recordHandshakeAttempt( Record $row ) :void {
		$this->setMetaTimestamp( $row, self::META_HANDSHAKE_ATTEMPT_AT );
	}

	/**
	 * @return false|int
	 */
	public function recordPingFailure( Record $row, int $httpCode, string $error ) {
		return $this->recordNotificationFailure( $row, $httpCode, $error );
	}

	/**
	 * @return false|int
	 */
	public function recordInterruptedNotificationExhaustion( Record $row ) {
		$now = Services::Request()->ts();
		return $this->recordNotificationFailure(
			$row,
			0,
			'Notification interrupted three times; retry deferred.',
			$now
		);
	}

	/**
	 * @return false|int
	 */
	public function recordExportTimeout( Record $row ) {
		$failures = $row->consecutive_failures + 1;
		$now = Services::Request()->ts();
		return $this->conditionalRowUpdate( [
			'queue_status'             => SitesDB::QUEUE_QUEUED,
			'last_export_failure_at'   => $now,
			'last_export_result_code'  => SitesDB::EXPORT_RESULT_TIMEOUT,
			'last_export_error'        => 'export_not_requested_before_grace_window',
			'consecutive_failures'     => $failures,
			'next_ping_at'             => $this->nextRetryAt( $failures ),
			'expected_export_by'       => 0,
			'lock_until'               => 0,
			'picked_at'                => 0,
		], '`id`=%d AND `status`=%s AND `deleted_at`=0 AND `queue_status`=%s AND `expected_export_by`=%d AND `last_ping_success_at`=%d AND `expected_export_by`>0 AND `expected_export_by`<=%d AND '.ExportWaitState::sqlUnsatisfiedSuccess(), [
			$row->id,
			SitesDB::STATUS_ACTIVE,
			SitesDB::QUEUE_WAITING_EXPORT,
			$row->expected_export_by,
			$row->last_ping_success_at,
			$now,
		] );
	}

	/**
	 * @return false|int
	 */
	public function recordExportReconciliation( Record $row ) {
		return $this->conditionalRowUpdate( [
			'queue_status'       => SitesDB::QUEUE_IDLE,
			'next_ping_at'       => $row->last_export_success_at + \DAY_IN_SECONDS,
			'expected_export_by' => 0,
			'lock_until'         => 0,
			'picked_at'          => 0,
		], '`id`=%d AND `status`=%s AND `deleted_at`=0 AND `queue_status`=%s AND `expected_export_by`=%d AND `last_ping_success_at`=%d AND `last_export_success_at`=%d AND '.ExportWaitState::sqlQualifyingSuccess(), [
			$row->id,
			SitesDB::STATUS_ACTIVE,
			SitesDB::QUEUE_WAITING_EXPORT,
			$row->expected_export_by,
			$row->last_ping_success_at,
			$row->last_export_success_at,
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
			$this->buildFilteredRowsOrderBySql( $orderBy, $orderDir )
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
			$hashToUrl[ $this->urlHash( $url ) ] = $url;
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

	private function metaWithoutRepairCooldowns( Record $row ) :array {
		$meta = \is_array( $row->meta ) ? $row->meta : [];
		unset( $meta[ self::META_EXPORT_SERVED_AT ], $meta[ self::META_HANDSHAKE_ATTEMPT_AT ] );
		return $meta;
	}

	private function buildConnectionResetData( Record $row ) :array {
		return [
			'import_id'               => '',
			'last_ping_failure_at'    => 0,
			'last_ping_http_code'     => 0,
			'last_ping_error'         => '',
			'last_export_failure_at'  => 0,
			'last_export_result_code' => '',
			'last_export_error'       => '',
			'consecutive_failures'    => 0,
			'meta'                    => $this->metaWithoutRepairCooldowns( $row ),
		];
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
			'url_hash'   => $this->urlHash( $url ),
			'status'     => SitesDB::STATUS_ACTIVE,
			'deleted_at' => 0,
		];

		$profileRef = $this->profileRefForRow( $row );
		if ( !$row instanceof Record || $row->profile_ref !== $profileRef ) {
			$data[ 'profile_ref' ] = $profileRef;
		}
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
		int $now,
		?int $profileRef = null
	) :array {
		$queueStatus = $sendInvite ? SitesDB::QUEUE_PENDING_INVITE : SitesDB::QUEUE_PENDING_CONNECTION;
		$data = [
			'url'                  => $url,
			'url_hash'             => $this->urlHash( $url ),
			'profile_ref'          => $profileRef ?? $this->profileRefForRow( $row ),
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
		if ( $row instanceof Record && $row->status === SitesDB::STATUS_DELETED && $row->deleted_at > 0 ) {
			$data = \array_merge( $data, $this->buildConnectionResetData( $row ) );
		}
		$meta = \is_array( $data[ 'meta' ] ?? null )
			? $data[ 'meta' ]
			: ( $row instanceof Record && \is_array( $row->meta ) ? $row->meta : [] );
		$data[ 'meta' ] = $sendInvite
			? ( new InvitationMetadata() )->replace( $meta, ( new InvitationMetadata() )->newCycle() )
			: ( new InvitationMetadata() )->remove( $meta );

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
		$base = $this->buildActiveInsertData( $url, $source, '', false, $now );
		return \array_merge(
			$base,
			$this->buildPendingClientSiteUpsertData( null, $url, $source, $sendInvite, $now, (int)$base[ 'profile_ref' ] )
		);
	}

	private function defaultProfileRef() :int {
		if ( $this->defaultProfileRef !== null ) {
			return $this->defaultProfileRef;
		}

		try {
			$profile = ( new ProfileRepository() )->ensureDefaultProfile();
			$profileRef = $profile instanceof ProfileRecord
				? $profile->id
				: 0;
			if ( $profileRef > 0 ) {
				$this->defaultProfileRef = $profileRef;
			}
			return $profileRef;
		}
		catch ( \Throwable $e ) {
			return 0;
		}
	}

	private function profileRefForRow( ?Record $row ) :int {
		if ( $row instanceof Record ) {
			try {
				$profileRef = ( new ProfileRepository() )->resolveProfileRefForSite( $row );
				return $profileRef > 0 ? $profileRef : $this->defaultProfileRef();
			}
			catch ( \Throwable $e ) {
				return $this->defaultProfileRef();
			}
		}

		return $this->defaultProfileRef();
	}

	private function queueRows( array $rows ) :int {
		if ( empty( $rows ) ) {
			return 0;
		}

		$now = Services::Request()->ts();
		$count = 0;
		foreach ( $rows as $row ) {
			if ( !$row instanceof Record ) {
				continue;
			}

			$result = $this->conditionalRowUpdate(
				$this->buildQueueDueData( $now ),
				'`id`=%d AND `status`=%s AND `deleted_at`=0 AND `queue_status`=%s',
				[ $row->id, SitesDB::STATUS_ACTIVE, SitesDB::QUEUE_IDLE ]
			);
			if ( $result === false ) {
				continue;
			}
			if ( $result === 1 ) {
				$count++;
				continue;
			}

			$current = $this->readRowForTransition( $row->id );
			if ( $current instanceof Record
				 && $current->status === SitesDB::STATUS_ACTIVE
				 && $current->deleted_at === 0
				 && $current->queue_status === SitesDB::QUEUE_QUEUED ) {
				$count++;
			}
		}

		return $count;
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

	/**
	 * @return Record[]
	 */
	private function selectDueWork( int $now, int $limit ) :array {
		return $this->selectRowsWithSql( $this->prepareSql(
			sprintf(
				"SELECT * FROM `%s`
				 WHERE `deleted_at`=0
				   AND `status`=%%s
				   AND (
				     (`queue_status` IN (%%s,%%s) AND `next_ping_at`<=%%d)
				     OR
				     (`queue_status`=%%s AND `next_ping_at`>0 AND `next_ping_at`<=%%d)
				   )
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
				 ORDER BY `picked_at` ASC, `id` ASC
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
				"SELECT * FROM `%s` FORCE INDEX (`waiting_export`)
				 WHERE `deleted_at`=0
				   AND `status`=%%s
				   AND `queue_status`=%%s
				   AND `expected_export_by`>0
				   AND `expected_export_by`<=%%d
				   AND %s
				 ORDER BY `expected_export_by` ASC, `id` ASC
				 LIMIT %%d",
				$this->db()->getTable(),
				ExportWaitState::sqlUnsatisfiedSuccess()
			),
			[
				SitesDB::STATUS_ACTIVE,
				SitesDB::QUEUE_WAITING_EXPORT,
				(int)$now,
				\max( 1, (int)$limit ),
			]
		) );
	}

	/**
	 * @return Record[]
	 */
	private function selectReconcilableWaitingExportRowsWithSql( int $limit ) :array {
		return $this->selectRowsWithSql( $this->prepareSql(
			sprintf(
				"SELECT * FROM `%s` FORCE INDEX (`waiting_export`)
				 WHERE `deleted_at`=0
				   AND `status`=%%s
				   AND `queue_status`=%%s
				   AND %s
				 ORDER BY `expected_export_by` ASC, `id` ASC
				 LIMIT %%d",
				$this->db()->getTable(),
				ExportWaitState::sqlQualifyingSuccess()
			),
			[
				SitesDB::STATUS_ACTIVE,
				SitesDB::QUEUE_WAITING_EXPORT,
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
		string $orderBySql
	) :array {
		return $this->selectRowsWithSql( sprintf(
			"SELECT * FROM `%s` %s ORDER BY %s LIMIT %d OFFSET %d",
			$this->db()->getTable(),
			$where,
			$orderBySql,
			\max( 1, $limit ),
			\max( 0, $offset )
		) );
	}

	private function buildFilteredRowsOrderBySql( string $orderBy, string $orderDir ) :string {
		if ( $orderBy === 'url' ) {
			return \sprintf(
				'%s %s, LOWER(`url`) %s, `id` DESC',
				$this->normalisedUrlSqlExpression(),
				$orderDir,
				$orderDir
			);
		}

		return \sprintf( '`%s` %s, `id` DESC', $this->sqlColumnName( $orderBy ), $orderDir );
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

	/**
	 * @return false|int
	 */
	private function recordNotificationResult(
		Record $operation,
		callable $dataBuilder,
		?int $exhaustedAt = null
	) {
		if ( !$this->isNotificationResultApplicable( $operation, $operation, $exhaustedAt ) ) {
			return 0;
		}
		$result = $this->conditionalNotificationResultUpdate(
			$operation,
			$operation,
			$dataBuilder( $operation ),
			$exhaustedAt
		);
		if ( $result === false || $result === 1 ) {
			return $result;
		}

		$current = $this->readRowForTransition( $operation->id );
		if ( $current === false ) {
			return false;
		}
		if ( !$current instanceof Record
			 || !$this->isNotificationResultApplicable( $current, $operation, $exhaustedAt ) ) {
			return 0;
		}

		$result = $this->conditionalNotificationResultUpdate(
			$current,
			$operation,
			$dataBuilder( $current ),
			$exhaustedAt
		);
		if ( $result === false || $result === 1 ) {
			return $result;
		}

		$current = $this->readRowForTransition( $operation->id );
		if ( $current === false ) {
			return false;
		}
		return $current instanceof Record
			   && $this->isNotificationResultApplicable( $current, $operation, $exhaustedAt ) ? false : 0;
	}

	/**
	 * @return false|int
	 */
	private function recordNotificationFailure(
		Record $row,
		int $httpCode,
		string $error,
		?int $exhaustedAt = null
	) {
		$now = $exhaustedAt ?? Services::Request()->ts();
		$error = $this->trimError( $error );
		return $this->recordNotificationResult( $row, function ( Record $current ) use ( $httpCode, $error, $now ) :array {
			$failures = $current->consecutive_failures + 1;
			return [
				'queue_status'         => SitesDB::QUEUE_QUEUED,
				'last_ping_failure_at' => $now,
				'last_ping_http_code'  => $httpCode,
				'last_ping_error'      => $error,
				'consecutive_failures' => $failures,
				'next_ping_at'         => $this->nextRetryAt( $failures ),
				'lock_until'           => 0,
				'picked_at'            => 0,
				'expected_export_by'   => 0,
				'meta'                 => ( new NotificationMetadata() )->reset( $current->meta ),
			];
		}, $exhaustedAt );
	}

	/**
	 * @return false|int
	 */
	private function conditionalNotificationResultUpdate(
		Record $metadataSource,
		Record $operation,
		array $data,
		?int $exhaustedAt = null
	) {
		$rawMeta = (string)( $metadataSource->getRawData()[ 'meta' ] ?? '' );
		$where = '`id`=%d AND `status`=%s AND `deleted_at`=0 AND `queue_status`=%s AND `picked_at`=%d AND `lock_until`=%d AND `last_ping_attempt_at`=%d AND BINARY `meta`=BINARY %s';
		$whereValues = [
			$operation->id,
			SitesDB::STATUS_ACTIVE,
			SitesDB::QUEUE_PROCESSING,
			$operation->picked_at,
			$operation->lock_until,
			$operation->last_ping_attempt_at,
			$rawMeta,
		];
		if ( $exhaustedAt !== null ) {
			$where .= ' AND `lock_until`>0 AND `lock_until`<=%d';
			$whereValues[] = $exhaustedAt;
		}
		return $this->conditionalRowUpdate(
			$data,
			$where,
			$whereValues
		);
	}

	private function isNotificationResultApplicable( Record $current, Record $operation, ?int $exhaustedAt ) :bool {
		if ( !$this->isSameNotificationOperation( $current, $operation ) ) {
			return false;
		}
		return $exhaustedAt === null
			   || ( $current->lock_until > 0
					&& $current->lock_until <= $exhaustedAt
					&& ( new NotificationMetadata() )->attemptsStarted( $current->meta ) >= NotificationMetadata::MAX_ATTEMPTS );
	}

	private function isSameNotificationOperation( Record $current, Record $operation ) :bool {
		$metadata = new NotificationMetadata();
		return $current->status === SitesDB::STATUS_ACTIVE
			   && $current->deleted_at === 0
			   && $current->queue_status === SitesDB::QUEUE_PROCESSING
			   && $current->picked_at === $operation->picked_at
			   && $current->lock_until === $operation->lock_until
			   && $current->last_ping_attempt_at === $operation->last_ping_attempt_at
			   && $metadata->attemptsStarted( $current->meta ) === $metadata->attemptsStarted( $operation->meta );
	}

	/**
	 * @return false|Record|null
	 */
	private function readRowForTransition( int $id ) {
		$wpdb = Services::WpDb()->loadWpdb();
		$wpdb->last_error = '';
		$rows = Services::WpDb()->selectCustom( $this->prepareSql(
			sprintf( 'SELECT * FROM `%s` WHERE `id`=%%d LIMIT 1', $this->db()->getTable() ),
			[ $id ]
		) );
		if ( !\is_array( $rows ) || $this->wpDbLastError() !== '' ) {
			return false;
		}
		$row = \reset( $rows );
		return \is_array( $row ) ? $this->db()->getRecord()->applyFromArray( $row ) : null;
	}

	private function wpDbLastError() :string {
		return (string)Services::WpDb()->loadWpdb()->last_error;
	}

	/**
	 * @return false|int
	 */
	private function conditionalRowUpdate( array $data, string $where, array $whereValues ) {
		$dbh = $this->dbOrNull();
		if ( !( $dbh instanceof SitesDB ) || !$dbh->isReady() || empty( $data ) ) {
			return false;
		}

		$data = $this->withUpdatedAt( $data, Services::Request()->ts() );
		$sets = [];
		$values = [];
		foreach ( $data as $column => $value ) {
			$value = $this->normaliseSqlValue( $value );
			$sets[] = \sprintf( '`%s`=%s', $this->sqlColumnName( $column ), $this->sqlPlaceholder( $value ) );
			$values[] = $value;
		}

		return Services::WpDb()->doSql( $this->prepareSql(
			\sprintf( 'UPDATE `%s` SET %s WHERE %s;', $dbh->getTable(), \implode( ',', $sets ), $where ),
			\array_merge( $values, $whereValues )
		) );
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

	/**
	 * @return false|int
	 */
	private function conditionalMetadataUpdate(
		Record $row,
		array $data,
		bool $requireClaim = false,
		?string $expectedQueueStatus = SitesDB::QUEUE_PENDING_INVITE
	) {
		$rawMeta = (string)( $row->getRawData()[ 'meta' ] ?? '' );
		$where = '`id`=%d AND `status`=%s AND `deleted_at`=0';
		$whereValues = [ $row->id, SitesDB::STATUS_ACTIVE ];
		if ( $expectedQueueStatus !== null ) {
			$where .= ' AND `queue_status`=%s';
			$whereValues[] = $expectedQueueStatus;
		}
		$where .= ' AND BINARY `meta`=BINARY %s';
		$whereValues[] = $rawMeta;
		if ( $requireClaim ) {
			$where .= ' AND `picked_at`=%d AND `lock_until`=%d';
			$whereValues[] = $row->picked_at;
			$whereValues[] = $row->lock_until;
		}

		return $this->conditionalRowUpdate( $data, $where, $whereValues );
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
		$search = $this->normaliseUrlForLookup( $search );
		if ( empty( $search ) ) {
			return '';
		}

		global $wpdb;
		$like = '%'.$wpdb->esc_like( $search ).'%';
		return $this->prepareSql(
			$this->normalisedUrlSqlExpression().' LIKE %s',
			[ $like ]
		);
	}

	private function normaliseUrlForLookup( string $url ) :string {
		$url = \strtolower( \trim( $url ) );
		foreach ( [ 'https://', 'http://' ] as $scheme ) {
			if ( \str_starts_with( $url, $scheme ) ) {
				$url = \substr( $url, \strlen( $scheme ) );
				break;
			}
		}

		return \str_starts_with( $url, 'www.' ) ? \substr( $url, 4 ) : $url;
	}

	private function normalisedUrlSqlExpression() :string {
		$lowerUrl = 'LOWER(`url`)';
		$withoutScheme = \sprintf(
			"(CASE WHEN %1\$s LIKE 'https://%%' THEN SUBSTRING(%1\$s, 9) WHEN %1\$s LIKE 'http://%%' THEN SUBSTRING(%1\$s, 8) ELSE %1\$s END)",
			$lowerUrl
		);

		return \sprintf(
			"(CASE WHEN %1\$s LIKE 'www.%%' THEN SUBSTRING(%1\$s, 5) ELSE %1\$s END)",
			$withoutScheme
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

	private function isRepairableConnectionRow( Record $row, int $now ) :bool {
		return $row->status === SitesDB::STATUS_ACTIVE
			   && ( ExportWaitState::isExpired( $row, $now ) || $this->hasQueuedOrIdleProblem( $row ) );
	}

	private function hasQueuedOrIdleProblem( Record $row ) :bool {
		return \in_array( $row->queue_status, [ SitesDB::QUEUE_QUEUED, SitesDB::QUEUE_IDLE ], true )
			   && ( $row->consecutive_failures > 0
					|| \max( $row->last_ping_failure_at, $row->last_export_failure_at ) > $row->last_export_success_at );
	}

	private function metaTimestampWithinCooldown( Record $row, string $key, int $cooldown ) :bool {
		$last = (int)( \is_array( $row->meta ) ? ( $row->meta[ $key ] ?? 0 ) : 0 );
		return $last > 0 && Services::Request()->ts() - $last < $cooldown;
	}

	private function setMetaTimestamp( Record $row, string $key ) :void {
		$current = $row;
		for ( $attempt = 0; $attempt < self::META_WRITE_ATTEMPTS; $attempt++ ) {
			$meta = \is_array( $current->meta ) ? $current->meta : [];
			$meta[ $key ] = Services::Request()->ts();
			$result = $this->conditionalMetadataUpdate( $current, [ 'meta' => $meta ], false, null );
			if ( $result === 1 ) {
				$row->meta = $meta;
				return;
			}
			if ( $result === false ) {
				return;
			}

			$current = $this->findById( $row->id, true );
			if ( !$current instanceof Record ) {
				return;
			}
		}
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
