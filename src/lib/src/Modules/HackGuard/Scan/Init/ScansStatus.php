<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Scans\Ops as ScansDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class ScansStatus {

	use PluginControllerConsumer;

	public function current() :string {
		return (string)Services::WpDb()->getVar(
			sprintf( "SELECT `scans`.`scan`
						FROM `%s` as `scans`
						INNER JOIN `%s` as `si`
							ON `si`.scan_ref = `scans`.id 
							AND `si`.`started_at`>0
						WHERE `scans`.`ready_at` > 0 
						  AND `scans`.`finished_at`=0
						LIMIT 1;",
				self::con()->db_con->scans->getTable(),
				self::con()->db_con->scan_items->getTable()
			)
		);
	}

	public function enqueued() :array {
		/** @var ScansDB\Select $selector */
		$selector = self::con()->db_con->scans->getQuerySelector();
		return $selector->filterByNotFinished()
						->addColumnToSelect( 'scan' )
						->setIsDistinct( true )
						->queryWithResult();
	}
}
