<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\Scans as ScansDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class ScansStatus {

	use ModConsumer;

	public function current() :string {
		return (string)Services::WpDb()->getVar(
			sprintf( "SELECT scans.scan
						FROM `%s` as scans
						INNER JOIN `%s` as `si`
							ON `si`.scan_ref = `scans`.id 
							AND `si`.`started_at`>0
						WHERE `scans`.`ready_at` > 0 
						  AND `scans`.`finished_at`=0
						LIMIT 1;",
				$this->mod()->getDbH_Scans()->getTableSchema()->table,
				$this->mod()->getDbH_ScanItems()->getTableSchema()->table
			)
		);
	}

	public function enqueued() :array {
		/** @var ScansDB\Ops\Select $selector */
		$selector = $this->mod()->getDbH_Scans()->getQuerySelector();
		return $selector->filterByNotFinished()
						->addColumnToSelect( 'scan' )
						->setIsDistinct( true )
						->queryWithResult();
	}
}
