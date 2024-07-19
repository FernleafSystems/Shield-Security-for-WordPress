<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Exceptions\NoQueueItems;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class QueueItems {

	use PluginControllerConsumer;

	/**
	 * @throws NoQueueItems
	 */
	public function next() :QueueItemVO {
		$result = Services::WpDb()->selectRow(
			sprintf( "SELECT `scans`.id as `scan_id`, `scans`.`scan`, `scans`.`meta`, `si`.`id` as `qitem_id`, `si`.`items`
						FROM `%s` as `scans`
						INNER JOIN `%s` as `si`
							ON `si`.`scan_ref` = `scans`.`id` 
							AND `si`.`started_at`=0
						WHERE `scans`.`ready_at` > 0 AND `scans`.`finished_at`=0
						ORDER BY `si`.`id` ASC
						LIMIT 1;",
				self::con()->db_con->scans->getTable(),
				self::con()->db_con->scan_items->getTable()
			)
		);
		if ( empty( $result ) ) {
			throw new NoQueueItems( 'No items remaining in queue to select.' );
		}
		foreach ( [ 'items', 'meta' ] as $key ) {
			$result[ $key ] = \json_decode( \base64_decode( $result[ $key ] ), true );
		}
		return ( new QueueItemVO() )->applyFromArray( \is_array( $result ) ? $result : [] );
	}

	public function hasNextItem() :bool {
		try {
			$this->next();
			$has = true;
		}
		catch ( NoQueueItems $e ) {
			$has = false;
		}
		return $has;
	}
}