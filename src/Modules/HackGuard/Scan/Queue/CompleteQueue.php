<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ScanItems\Ops as ScanItemsDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class CompleteQueue {

	use PluginControllerConsumer;

	public function complete() {
		$con = self::con();
		/** @var ScanItemsDB\Delete $deleter */
		$deleter = $con->db_con->scan_items->getQueryDeleter();
		$deleter->filterByFinished()->query();

		$activeCount = $con->db_con->scans->getQuerySelector()
			->filterByNotFinished()
			->addWhereIn( 'status', [ 'queued', 'building', 'built', 'running' ] )
			->count();
		if ( $activeCount > 0 ) {
			if ( $con->db_con->scans->getQuerySelector()->filterByStatus( 'queued' )->count() > 0 ) {
				$con->comps->scans_queue->getQueueBuilder()->dispatch();
			}
			return;
		}

		if ( $con->opts->optGet( 'is_scan_cron' ) && !wp_next_scheduled( $con->prefix( 'post_scan' ) ) ) {
			wp_schedule_single_event( Services::Request()->ts() + 5, $con->prefix( 'post_scan' ) );
		}

		do_action( 'shield/scan_queue_completed' );

		self::con()->opts->optSet( 'is_scan_cron', false );
	}
}
