<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\{
	DB\ScanItems\Ops as ScanItemsDB,
	Options,
	Scan\Exceptions\NoQueueItems
};
use FernleafSystems\Wordpress\Services\Services;
use WP_CLI;

class ProcessQueueWpcli extends Shield\Modules\Base\Common\ExecOnceModConsumer {

	protected function canRun() :bool {
		return Services::WpGeneral()->isWpCli();
	}

	protected function run() {
		$mod = $this->getCon()->getModule_HackGuard();
		$scansCon = $mod->getScansCon();
		/** @var Options $opts */
		$opts = $this->getOptions();

		foreach ( array_keys( $opts->getScansToBuild() ) as $scan ) {
			$opts->addRemoveScanToBuild( $scan, false );
			$mod->saveModOptions();
			try {
				WP_CLI::log( sprintf( 'Building scan items for scan: %s',
					$scansCon->getScanCon( $scan )->getScanName() ) );
				( new QueueInit() )
					->setMod( $mod )
					->init( $scan );
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
				$qItem = ( new QueueItems() )
					->setMod( $mod )
					->next();
				( new ProcessQueueItem() )
					->setMod( $mod )
					->run( $qItem );
			}
			catch ( NoQueueItems $e ) {
				$qItem = null;
			}
			$progress->tick();
		} while ( !empty( $qItem ) );

		( new CompleteQueue() )
			->setMod( $mod )
			->complete();

		$progress->finish();
		WP_CLI::log( 'Scans Complete.' );
	}
}