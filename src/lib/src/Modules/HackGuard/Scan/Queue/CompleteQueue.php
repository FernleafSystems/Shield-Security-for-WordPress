<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\ScanItems as ScanItemsDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Options;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Services\Services;

class CompleteQueue {

	use ModConsumer;

	public function complete() {
		$con = $this->getCon();

		/** @var ScanItemsDB\Ops\Delete $deleter */
		$deleter = $con->getModule_HackGuard()->getDbH_ScanItems()->getQueryDeleter();
		$deleter->filterByFinished()->query();

		/** @var Options $opts */
		$opts = $this->getOptions();
		if ( $opts->getOpt( 'is_scan_cron' ) && !wp_next_scheduled( $con->prefix( 'post_scan' ) ) ) {
			wp_schedule_single_event(
				Services::Request()->ts() + 5,
				$con->prefix( 'post_scan' )
			);
		}
		$opts->setIsScanCron( false );
	}
}
