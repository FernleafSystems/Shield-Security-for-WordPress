<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\ScanItems as ScanItemsDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Services\Services;

class CompleteQueue {

	use ModConsumer;

	public function complete() {
		/** @var ScanItemsDB\Ops\Delete $deleter */
		$deleter = $this->mod()->getDbH_ScanItems()->getQueryDeleter();
		$deleter->filterByFinished()->query();

		$hook = self::con()->prefix( 'post_scan' );
		if ( $this->opts()->getOpt( 'is_scan_cron' ) && !wp_next_scheduled( $hook ) ) {
			wp_schedule_single_event( Services::Request()->ts() + 5, $hook );
		}

		$this->opts()->setIsScanCron( false );
	}
}
