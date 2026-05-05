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

	public function ensureLegacyImported( bool $includeOldQueueState = true ) :void {
		$dbh = $this->db();
		if ( !$dbh->isReady() ) {
			return;
		}

		$fallbackUrls = $this->canonicalLegacyWhitelistUrls();
		$oldQueuedUrls = $includeOldQueueState ? $this->canonicalOldQueueUrls( $fallbackUrls ) : [];
		$urlIds = $this->legacyImportIds();

		foreach ( $fallbackUrls as $url ) {
			$this->upsertActive(
				$url,
				SitesDB::SOURCE_LEGACY_OPTION,
				(string)( $urlIds[ \hash( 'md5', $url ) ] ?? '' ),
				\in_array( $url, $oldQueuedUrls, true )
			);
		}

		$this->mirrorActiveRowsToFallback();
		$this->mirrorImportIdsToFallback();

		self::con()->opts->optSet( self::MIGRATED_AT_OPTION, Services::Request()->ts() );
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
		if ( empty( $url ) || !$this->db()->isReady() ) {
			return null;
		}

		$now = Services::Request()->ts();
		$row = $this->findByUrl( $url, true );
		$data = [
			'url'          => $url,
			'url_hash'     => \hash( 'md5', $url ),
			'status'       => SitesDB::STATUS_ACTIVE,
			'deleted_at'   => 0,
			'updated_at'   => $now,
		];

		if ( !empty( $source ) && ( empty( $row ) || empty( $row->source ) ) ) {
			$data[ 'source' ] = $source;
		}
		if ( !empty( $importID ) ) {
			$data[ 'import_id' ] = $importID;
		}
		if ( empty( $row ) || $markDue || ( $row->next_ping_at <= 0 && $row->queue_status !== SitesDB::QUEUE_WAITING_EXPORT ) ) {
			$data = \array_merge( $data, $this->buildQueueDueData( $now ) );
		}

		if ( $row instanceof Record ) {
			$this->updateById( $row->id, $data );
			return $this->findById( $row->id, true );
		}

		$record = $this->db()->getRecord();
		foreach ( \array_merge( [
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
		], $data ) as $key => $value ) {
			$record->{$key} = $value;
		}

		$this->db()->getQueryInserter()->setUseHelper()->insert( $record );
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
		$this->mirrorActiveRowsToFallback();
		$this->mirrorImportIdsToFallback();
		$this->storeOptionsIfChanged();
	}

	public function queueSiteIds( array $ids ) :int {
		$count = 0;
		$now = Services::Request()->ts();
		foreach ( $this->findActiveByIds( $ids ) as $row ) {
			if ( $this->updateById( $row->id, $this->buildQueueDueData( $now ) ) ) {
				$count++;
			}
		}
		return $count;
	}

	public function queueAllActive() :int {
		return $this->queueSiteIds( \array_map( static fn( Record $row ) :int => $row->id, $this->selectActiveRows() ) );
	}

	public function syncFallbackSettings() :void {
		$this->mirrorActiveRowsToFallback();
		$this->mirrorImportIdsToFallback();
		$this->storeOptionsIfChanged();
	}

	/**
	 * @return Record[]
	 */
	public function claimDueRows( int $limit, int $lockUntil ) :array {
		$now = Services::Request()->ts();
		$table = $this->db()->getTable();
		$rows = $this->selectRowsWithSql( sprintf(
			"SELECT * FROM `%s`
			 WHERE `deleted_at`=0
			   AND `status`='%s'
			   AND `queue_status` IN ('%s','%s')
			   AND `next_ping_at`<=%d
			   AND (`lock_until`=0 OR `lock_until`<=%d)
			 ORDER BY `priority` DESC, `next_ping_at` ASC, `id` ASC
			 LIMIT %d",
			$table,
			esc_sql( SitesDB::STATUS_ACTIVE ),
			esc_sql( SitesDB::QUEUE_IDLE ),
			esc_sql( SitesDB::QUEUE_QUEUED ),
			$now,
			$now,
			\max( 1, $limit )
		) );

		foreach ( $rows as $row ) {
			$this->updateById( $row->id, [
				'queue_status' => SitesDB::QUEUE_PROCESSING,
				'picked_at'    => $now,
				'lock_until'   => $lockUntil,
			] );
		}

		return \array_map(
			fn( Record $row ) => $this->findById( $row->id, true ) ?? $row,
			$rows
		);
	}

	/**
	 * @return Record[]
	 */
	public function selectExpiredWaitingExportRows( int $limit ) :array {
		$now = Services::Request()->ts();
		return $this->selectRowsWithSql( sprintf(
			"SELECT * FROM `%s`
			 WHERE `deleted_at`=0
			   AND `status`='%s'
			   AND `queue_status`='%s'
			   AND `expected_export_by`>0
			   AND `expected_export_by`<=%d
			   AND (`last_export_success_at`=0 OR `last_export_success_at`<`last_ping_success_at`)
			 ORDER BY `expected_export_by` ASC, `id` ASC
			 LIMIT %d",
			$this->db()->getTable(),
			esc_sql( SitesDB::STATUS_ACTIVE ),
			esc_sql( SitesDB::QUEUE_WAITING_EXPORT ),
			$now,
			\max( 1, $limit )
		) );
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
		$this->mirrorImportIdsToFallback();
		$this->storeOptionsIfChanged();
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
		return (int)Services::WpDb()->getVar( sprintf( 'SELECT COUNT(*) FROM `%s`', $this->db()->getTable() ) );
	}

	public function countFilteredRows( string $search = '' ) :int {
		$where = $this->buildSearchWhere( $search );
		return (int)Services::WpDb()->getVar( sprintf(
			'SELECT COUNT(*) FROM `%s` %s',
			$this->db()->getTable(),
			$where
		) );
	}

	/**
	 * @return Record[]
	 */
	public function selectFilteredRows( string $search, int $offset, int $limit, string $orderBy, string $orderDir ) :array {
		$allowedOrder = \array_flip( $this->db()->getTableSchema()->getColumnNames() );
		$orderBy = isset( $allowedOrder[ $orderBy ] ) ? $orderBy : 'updated_at';
		$orderDir = \strtoupper( $orderDir ) === 'ASC' ? 'ASC' : 'DESC';

		return $this->selectRowsWithSql( sprintf(
			"SELECT * FROM `%s` %s ORDER BY `%s` %s, `id` DESC LIMIT %d OFFSET %d",
			$this->db()->getTable(),
			$this->buildSearchWhere( $search ),
			$orderBy,
			$orderDir,
			\max( 1, $limit ),
			\max( 0, $offset )
		) );
	}

	public function findByUrl( string $url, bool $includeDeleted = false ) :?Record {
		if ( !$this->db()->isReady() ) {
			return null;
		}
		$url = $this->canonicalizeUrl( $url );
		return empty( $url ) ? null : $this->findByHash( \hash( 'md5', $url ), $includeDeleted );
	}

	public function findById( int $id, bool $includeDeleted = false ) :?Record {
		if ( !$this->db()->isReady() ) {
			return null;
		}
		return $this->db()
					->getQuerySelector()
					->setIncludeSoftDeleted( $includeDeleted )
					->addWhereEquals( 'id', $id )
					->first();
	}

	private function findByHash( string $hash, bool $includeDeleted = false ) :?Record {
		return $this->db()
					->getQuerySelector()
					->setIncludeSoftDeleted( $includeDeleted )
					->addWhereEquals( 'url_hash', $hash )
					->first();
	}

	/**
	 * @return Record[]
	 */
	private function findActiveByIds( array $ids ) :array {
		$ids = \array_values( \array_unique( \array_filter( \array_map( '\intval', $ids ), static fn( int $id ) :bool => $id > 0 ) ) );
		if ( empty( $ids ) ) {
			return [];
		}

		return $this->db()
					->getQuerySelector()
					->addWhereEquals( 'status', SitesDB::STATUS_ACTIVE )
					->addWhereIn( 'id', $ids )
					->queryWithResult() ?? [];
	}

	private function mirrorActiveRowsToFallback() :void {
		$urls = \array_values( \array_unique( \array_map(
			static fn( Record $row ) :string => $row->url,
			$this->selectActiveRows()
		) ) );
		self::con()->opts->optSet( 'importexport_whitelist', $urls );
	}

	private function mirrorImportIdsToFallback() :void {
		$urlIds = [];
		foreach ( $this->selectActiveRows() as $row ) {
			if ( !empty( $row->import_id ) ) {
				$urlIds[ \hash( 'md5', $row->url ) ] = $row->import_id;
			}
		}
		self::con()->opts->optSet( 'import_url_ids', $urlIds );
	}

	private function storeOptionsIfChanged() :void {
		if ( self::con()->opts->hasChanges() ) {
			self::con()->opts->store();
		}
	}

	private function canonicalLegacyWhitelistUrls() :array {
		$raw = self::con()->opts->optGet( 'importexport_whitelist' );
		return \array_values( \array_unique( \array_filter( \array_map(
			fn( $url ) :string => $this->canonicalizeUrl( (string)$url ),
			\is_array( $raw ) ? $raw : []
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

	private function updateById( int $id, array $data ) :bool {
		if ( isset( $data[ 'meta' ] ) && \is_array( $data[ 'meta' ] ) ) {
			$data[ 'meta' ] = $this->db()->getRecord()->arrayDataWrap( $data[ 'meta' ] ) ?? '';
		}
		return $this->db()
					->getQueryUpdater()
					->updateById( $id, $data );
	}

	private function buildSearchWhere( string $search ) :string {
		$search = \trim( $search );
		if ( empty( $search ) ) {
			return '';
		}

		$search = esc_sql( $search );
		return sprintf(
			"WHERE `url` LIKE '%%%s%%' OR `status` LIKE '%%%s%%' OR `queue_status` LIKE '%%%s%%' OR `last_ping_error` LIKE '%%%s%%' OR `last_export_error` LIKE '%%%s%%'",
			$search,
			$search,
			$search,
			$search,
			$search
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
}
