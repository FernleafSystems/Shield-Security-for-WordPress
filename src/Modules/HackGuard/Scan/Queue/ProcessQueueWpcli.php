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

		while ( true ) {
			$queuedScan = null;
			if ( !( new QueueItems() )->hasNextItem() ) {
				$queuedScan = $con->db_con->scans->getQuerySelector()
							 ->filterByStatus( 'queued' )
							 ->setOrderBy( 'created_at', 'ASC', true )
							 ->first();
				if ( empty( $queuedScan ) ) {
					break;
				}
			}

			try {
				if ( !empty( $queuedScan ) ) {
					WP_CLI::log( sprintf( __( 'Building scan items for scan: %s', 'wp-simple-firewall' ),
						$con->comps->scans->getScanCon( $queuedScan->scan )->getScanName()
					) );
					( new QueueInit() )->init( (int)$queuedScan->id );
				}

				WP_CLI::log( __( 'Starting scans...', 'wp-simple-firewall' ) );

				/** @var ScanItemsDB\Select $selector */
				$selector = $con->db_con->scan_items->getQuerySelector();
				$progress = WP_CLI\Utils\make_progress_bar( __( 'Scans progress', 'wp-simple-firewall' ),
					\array_sum( $selector->countAllForEachScan() ) );

				while ( true ) {
					try {
						$qItem = ( new QueueItems() )->next();
					}
					catch ( NoQueueItems $e ) {
						break;
					}
					( new ProcessQueueItem() )->run( $qItem );
					$progress->tick();
				}

				$progress->finish();
				( new CompleteQueue() )->complete();
				WP_CLI::log( __( 'Scans complete.', 'wp-simple-firewall' ) );
			}
			catch ( \Throwable $e ) {
				if ( !empty( $queuedScan ) ) {
					( new RunState() )->markFailed( (int)$queuedScan->id, $e->getMessage() );
				}
				else {
					WP_CLI::warning( $e->getMessage() );
					break;
				}
			}
		}
	}
}
