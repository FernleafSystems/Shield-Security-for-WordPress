<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class CompleteQueue
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue
 */
class CompleteQueue {

	use Databases\Base\HandlerConsumer;
	use ModConsumer;

	/**
	 * Take care here not to confuse the 2x DB Handlers
	 */
	public function complete() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$con = $this->getCon();
		/** @var Databases\ScanQueue\Handler $dbh */
		$dbh = $this->getDbHandler();
		$selector = $dbh->getQuerySelector();

		foreach ( $selector->getDistinctForColumn( 'scan' ) as $scanSlug ) {

			$scanCon = $mod->getScanCon( $scanSlug );

			$resultsSet = ( new CollateResults() )
				->setScanController( $scanCon )
				->setDbHandler( $dbh )
				->collate( $scanSlug );

			$con->fireEvent( 'scan_run', [ 'audit_params' => [ 'scan' => $scanCon->getScanName() ] ] );

			( new HackGuard\Scan\Results\ResultsUpdate() )
				->setScanController( $scanCon )
				->update( $resultsSet );

			if ( $resultsSet->countItems() > 0 ) {

				$items = $resultsSet->countItems() > 30 ?
					__( 'Only the first 30 items are shown.', 'wp-simple-firewall' )
					: __( 'The following items were discovered.', 'wp-simple-firewall' );

				$items .= ' "'.
						  implode( '", "', array_map( function ( $item ) {
							  return $item->getDescriptionForAudit();
						  }, array_slice( $resultsSet->getItems(), 0, 30 ) ) )
						  .'"';

				$con->fireEvent(
					'scan_items_found',
					[
						'audit_params' => [
							'scan'  => $scanCon->getScanName(),
							'items' => $items
						]
					]
				);
			}

			/** @var Databases\ScanQueue\Delete $deleter */
			$deleter = $dbh->getQueryDeleter();
			$deleter->filterByScan( $scanSlug )->query();
		}

		/** @var HackGuard\Options $opts */
		$opts = $this->getOptions();
		if ( $opts->isScanCron() && !wp_next_scheduled( $con->prefix( 'post_scan' ) ) ) {
			wp_schedule_single_event(
				Services::Request()->ts() + 5,
				$con->prefix( 'post_scan' )
			);
		}
		$opts->setIsScanCron( false );
	}
}
