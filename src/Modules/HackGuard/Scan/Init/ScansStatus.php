<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\ScanStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class ScansStatus {

	use PluginControllerConsumer;

	private ?array $activeSnapshot = null;

	private ?array $activeScans = null;

	public function current() :string {
		return (string)$this->activeSnapshot()[ 'current' ];
	}

	public function enqueued() :array {
		return $this->activeSnapshot()[ 'enqueued' ];
	}

	/**
	 * @return array{current:string,enqueued:string[]}
	 */
	public function activeSnapshot() :array {
		return $this->activeSnapshot ??= $this->loadActiveSnapshot();
	}

	/**
	 * @return list<array{id:int,scan:string,status:string,scope_type:string,scope_key:string,created_at:int,started_at:int,ready_at:int,last_process_at:int}>
	 */
	public function activeScans() :array {
		return $this->activeScans ??= $this->loadActiveScans();
	}

	/**
	 * @return array{current:string,enqueued:string[]}
	 */
	private function loadActiveSnapshot() :array {
		$rows = Services::WpDb()->selectCustom(
			sprintf( "SELECT `scans`.`scan`, `scans`.`status`, `scans`.`created_at`
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

		$current = '';
		$enqueued = [];
		foreach ( $rows as $row ) {
			$scan = (string)$row[ 'scan' ];
			if ( $scan === '' ) {
				continue;
			}
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
	 * @return list<array{id:int,scan:string,status:string,scope_type:string,scope_key:string,created_at:int,started_at:int,ready_at:int,last_process_at:int}>
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
			if ( !\is_array( $row ) ) {
				continue;
			}
			$id = (int)( $row[ 'id' ] ?? 0 );
			if ( $id <= 0 || (string)( $row[ 'scan' ] ?? '' ) === '' ) {
				continue;
			}
			$activeScans[] = [
				'id'              => $id,
				'scan'            => (string)$row[ 'scan' ],
				'status'          => (string)( $row[ 'status' ] ?? '' ),
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
