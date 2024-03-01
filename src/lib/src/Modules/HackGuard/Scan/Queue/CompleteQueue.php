<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ScanItems\Ops as ScanItemsDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class CompleteQueue {

	use PluginControllerConsumer;

	public function complete() {
		/** @var ScanItemsDB\Delete $deleter */
		$deleter = self::con()->db_con->dbhScanItems()->getQueryDeleter();
		$deleter->filterByFinished()->query();

		$hook = self::con()->prefix( 'post_scan' );
		if ( self::con()->opts->optGet( 'is_scan_cron' ) && !wp_next_scheduled( $hook ) ) {
			wp_schedule_single_event( Services::Request()->ts() + 5, $hook );
		}

		self::con()->opts->optSet( 'is_scan_cron', false );
	}
}