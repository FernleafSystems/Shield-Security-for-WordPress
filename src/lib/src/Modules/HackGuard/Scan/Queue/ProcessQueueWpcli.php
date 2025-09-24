<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ScanItems\Ops as ScanItemsDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Exceptions\NoQueueItems;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;
use WP_CLI;

class ProcessQueueWpcli {

	use ExecOnce;
	use PluginControllerConsumer;

	protected function canRun() :bool {
		return Services::WpGeneral()->isWpCli();
	}

	protected function run() {
		$con = self::con();

		foreach ( \array_keys( $con->comps->scans->getScansToBuild() ) as $scan ) {
			$con->comps->scans->addRemoveScanToBuild( $scan, false );
			$con->opts->store();
			try {
				WP_CLI::log( sprintf( __( 'Building scan items for scan: %s', 'wp-simple-firewall' ),
					$con->comps->scans->getScanCon( $scan )->getScanName()
				) );
				( new QueueInit() )->init( $scan );
			}
			catch ( \Exception $e ) {
			}
		}

		WP_CLI::log( __( 'Starting scans…', 'wp-simple-firewall' ) );

		/** @var ScanItemsDB\Select $selector */
		$selector = $con->db_con->scan_items->getQuerySelector();
		$progress = WP_CLI\Utils\make_progress_bar( __( 'Scans progress', 'wp-simple-firewall' ),
			\array_sum( $selector->countAllForEachScan() ) );

		do {
			try {
				$qItem = ( new QueueItems() )->next();
				( new ProcessQueueItem() )->run( $qItem );
			}
			catch ( NoQueueItems $e ) {
				$qItem = null;
			}
			$progress->tick();
		} while ( !empty( $qItem ) );

		( new CompleteQueue() )->complete();

		$progress->finish();
		WP_CLI::log( __( 'Scans complete.', 'wp-simple-firewall' ) );
	}
}
