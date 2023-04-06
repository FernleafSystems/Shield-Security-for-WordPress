<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\{
	DB\ScanItems\Ops as ScanItemsDB,
	Scan\Exceptions\NoQueueItems
};
use FernleafSystems\Wordpress\Services\Services;
use WP_CLI;

class ProcessQueueWpcli {

	use ExecOnce;
	use Shield\Modules\HackGuard\ModConsumer;

	protected function canRun() :bool {
		return Services::WpGeneral()->isWpCli();
	}

	protected function run() {
		$mod = $this->mod();

		foreach ( array_keys( $this->opts()->getScansToBuild() ) as $scan ) {
			$this->opts()->addRemoveScanToBuild( $scan, false );
			$mod->saveModOptions();
			try {
				WP_CLI::log( sprintf( 'Building scan items for scan: %s',
					$mod->getScansCon()->getScanCon( $scan )->getScanName() ) );
				( new QueueInit() )->init( $scan );
			}
			catch ( \Exception $e ) {
			}
		}

		WP_CLI::log( 'Starting Scans...' );

		/** @var ScanItemsDB\Select $selector */
		$selector = $mod->getDbH_ScanItems()->getQuerySelector();
		$progress = WP_CLI\Utils\make_progress_bar( 'Scans Progress',
			array_sum( $selector->countAllForEachScan() ) );

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
		WP_CLI::log( 'Scans Complete.' );
	}
}