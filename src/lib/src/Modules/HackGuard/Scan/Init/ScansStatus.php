<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\Scans as ScansDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class ScansStatus {

	use ModConsumer;

	public function current() :string {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		return (string)Services::WpDb()->getVar(
			sprintf( "SELECT scans.scan
						FROM `%s` as scans
						INNER JOIN `%s` as `si`
							ON `si`.scan_ref = `scans`.id 
							AND `si`.`started_at`>0
						WHERE `scans`.`ready_at` > 0 
						  AND `scans`.`finished_at`=0
						LIMIT 1;",
				$mod->getDbH_Scans()->getTableSchema()->table,
				$mod->getDbH_ScanItems()->getTableSchema()->table
			)
		);
	}

	public function enqueued() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		/** @var ScansDB\Ops\Select $selector */
		$selector = $mod->getDbH_Scans()->getQuerySelector();
		return $selector->filterByNotFinished()
						->addColumnToSelect( 'scan' )
						->setIsDistinct( true )
						->queryWithResult();
	}
}
