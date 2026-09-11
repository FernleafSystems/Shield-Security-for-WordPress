<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Exceptions\NoQueueItems;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\ScanStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class QueueItems {

	use PluginControllerConsumer;

	/**
	 * @throws NoQueueItems
	 */
	public function next() :QueueItemVO {
		$attempts = 0;
		while ( $attempts < 10 ) {
			$attempts++;
			$result = Services::WpDb()->selectRow(
				sprintf( "SELECT `scans`.id as `scan_id`,
							 `scans`.`scan`,
							 `scans`.`scope_type`,
							 `scans`.`scope_key`,
							 `scans`.`run_trigger`,
							 `scans`.`started_at` AS `scan_started_at`,
							 `scans`.`meta`,
							 `si`.`id` as `qitem_id`,
							 `si`.`attempts`,
							 `si`.`items`
						FROM `%s` as `scans`
						INNER JOIN `%s` as `si`
							ON `si`.`scan_ref` = `scans`.`id` 
							AND `si`.`started_at`=0
							AND `si`.`finished_at`=0
						WHERE `scans`.`id` = (%s)
						ORDER BY `si`.`id` ASC
						LIMIT 1;",
					self::con()->db_con->scans->getTable(),
					self::con()->db_con->scan_items->getTable(),
					$this->oldestReadyScanIdSql()
				)
			);
			if ( empty( $result ) ) {
				throw new NoQueueItems( __( 'No items remaining in queue to select.', 'wp-simple-firewall' ) );
			}

			$item = ( new QueueItemVO() )->applyFromArray( $result );
			if ( $this->claim( $item ) ) {
				$item->attempts = $item->attempts + 1;
				return $item;
			}
		}

		throw new NoQueueItems( __( 'No items remaining in queue to claim.', 'wp-simple-firewall' ) );
	}

	public function hasNextItem() :bool {
		return (int)Services::WpDb()->getVar(
			sprintf( "SELECT 1
						FROM `%s` as `si`
						WHERE `si`.`scan_ref` = (%s)
						  AND `si`.`started_at`=0
						  AND `si`.`finished_at`=0
						LIMIT 1;",
				self::con()->db_con->scan_items->getTable(),
				$this->oldestReadyScanIdSql()
			)
		) === 1;
	}

	private function oldestReadyScanIdSql() :string {
		return sprintf( "SELECT `oldest_scan`.`id`
						FROM `%s` as `oldest_scan`
						WHERE `oldest_scan`.`status` IN (%s)
						  AND `oldest_scan`.`ready_at` > 0
						  AND `oldest_scan`.`finished_at`=0
						ORDER BY `oldest_scan`.`created_at` ASC, `oldest_scan`.`id` ASC
						LIMIT 1",
			self::con()->db_con->scans->getTable(),
			ScanStatus::sqlList( ScanStatus::READY )
		);
	}

	private function claim( QueueItemVO $item ) :bool {
		return (int)Services::WpDb()->doSql(
			sprintf( "UPDATE `%s`
					SET `started_at`=%d,
						`attempts`=`attempts`+1
					WHERE `id`=%d
					  AND `started_at`=0
					  AND `finished_at`=0;",
				self::con()->db_con->scan_items->getTable(),
				Services::Request()->ts(),
				$item->qitem_id
			)
		) > 0;
	}
}
