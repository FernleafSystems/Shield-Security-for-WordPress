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
						WHERE `scans`.`status` IN ('queued','building','running')
						  AND `scans`.`finished_at`=0
						ORDER BY CASE WHEN `scans`.`status` IN ('building','running') THEN 0 ELSE 1 END ASC,
								 `scans`.`created_at` ASC
						LIMIT 1;",
				self::con()->db_con->scans->getTable()
			)
		);
	}

	public function enqueued() :array {
		/** @var ScansDB\Select $selector */
		$selector = self::con()->db_con->scans->getQuerySelector();
		return $selector->filterByNotFinished()
						->addWhereIn( 'status', [ 'queued', 'building', 'running' ] )
						->addColumnToSelect( 'scan' )
						->setIsDistinct( true )
						->queryWithResult();
	}
}
