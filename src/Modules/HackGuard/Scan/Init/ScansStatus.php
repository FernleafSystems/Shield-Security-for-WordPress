<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\ScanStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class ScansStatus {

	use PluginControllerConsumer;

	private ?array $activeSnapshot = null;

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
}
