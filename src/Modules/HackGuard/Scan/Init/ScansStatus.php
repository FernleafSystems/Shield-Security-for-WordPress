<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\ScanStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @phpstan-type ActiveScanStatus 'queued'|'building'|'built'|'running'
 * @phpstan-type ActiveScanRow array{id:int,scan:string,status:ActiveScanStatus,scope_type:string,scope_key:string,created_at:int,started_at:int,ready_at:int,last_process_at:int}
 */
class ScansStatus {

	use PluginControllerConsumer;

	/** @var ?array{current:string,enqueued:list<string>} */
	private ?array $activeSnapshot = null;

	/** @var ?list<ActiveScanRow> */
	private ?array $activeScans = null;

	/** @return list<string> */
	public function enqueued() :array {
		return $this->activeSnapshot()[ 'enqueued' ];
	}

	/**
	 * @return array{current:string,enqueued:list<string>}
	 */
	public function activeSnapshot() :array {
		return $this->activeSnapshot ??= $this->loadActiveSnapshot();
	}

	/**
	 * @return list<ActiveScanRow>
	 */
	public function activeScans() :array {
		return $this->activeScans ??= $this->loadActiveScans();
	}

	/**
	 * @throws \RuntimeException
	 */
	public function hasActiveAfs() :bool {
		global $wpdb;

		try {
			$rows = Services::WpDb()->selectCustom( \sprintf(
				"SELECT `scans`.`id`
					FROM `%s` AS `scans`
					WHERE `scans`.`scan`='afs'
					  AND `scans`.`status` IN (%s)
					  AND `scans`.`finished_at`=0
					LIMIT 1;",
				self::con()->db_con->scans->getTable(),
				ScanStatus::sqlList( ScanStatus::ACTIVE )
			) );
		}
		catch ( \Throwable $e ) {
			throw new \RuntimeException( 'Active AFS status query failed.', 0, $e );
		}

		if ( !\is_array( $rows )
			 || ( \is_object( $wpdb ) && (string)( $wpdb->last_error ?? '' ) !== '' ) ) {
			throw new \RuntimeException( 'Active AFS status query failed.' );
		}

		return !empty( $rows );
	}

	/**
	 * @return array{current:string,enqueued:list<string>}
	 */
	private function loadActiveSnapshot() :array {
		$current = '';
		$enqueued = [];
		foreach ( $this->activeScans() as $row ) {
			$scan = $row[ 'scan' ];
			if ( $current === '' ) {
				$current = $scan;
			}
			$enqueued[] = $scan;
		}

		return [
			'current'  => $current,
			'enqueued' => \array_values( \array_unique( $enqueued ) ),
		];
	}

	/**
	 * @return list<ActiveScanRow>
	 */
	private function loadActiveScans() :array {
		$rows = Services::WpDb()->selectCustom(
			sprintf( "SELECT `scans`.`id`,
							 `scans`.`scan`,
							 `scans`.`status`,
							 `scans`.`scope_type`,
							 `scans`.`scope_key`,
							 `scans`.`created_at`,
							 `scans`.`started_at`,
							 `scans`.`ready_at`,
							 `scans`.`last_process_at`
						FROM `%s` as `scans`
						WHERE `scans`.`status` IN (%s)
						  AND `scans`.`finished_at`=0
						ORDER BY CASE WHEN `scans`.`status` IN (%s) THEN 0 ELSE 1 END ASC,
								 `scans`.`created_at` ASC,
								 `scans`.`id` ASC;",
				self::con()->db_con->scans->getTable(),
				ScanStatus::sqlList( ScanStatus::ACTIVE ),
				ScanStatus::sqlList( ScanStatus::CURRENT )
			)
		) ?: [];

		$activeScans = [];
		foreach ( $rows as $row ) {
			$id = (int)( $row[ 'id' ] ?? 0 );
			$status = (string)( $row[ 'status' ] ?? '' );
			if ( $id <= 0
				 || (string)( $row[ 'scan' ] ?? '' ) === ''
				 || !\in_array( $status, ScanStatus::ACTIVE, true ) ) {
				continue;
			}
			$activeScans[] = [
				'id'              => $id,
				'scan'            => (string)$row[ 'scan' ],
				'status'          => $status,
				'scope_type'      => (string)( $row[ 'scope_type' ] ?? 'full' ),
				'scope_key'       => (string)( $row[ 'scope_key' ] ?? '' ),
				'created_at'      => (int)( $row[ 'created_at' ] ?? 0 ),
				'started_at'      => (int)( $row[ 'started_at' ] ?? 0 ),
				'ready_at'        => (int)( $row[ 'ready_at' ] ?? 0 ),
				'last_process_at' => (int)( $row[ 'last_process_at' ] ?? 0 ),
			];
		}

		return $activeScans;
	}

}
