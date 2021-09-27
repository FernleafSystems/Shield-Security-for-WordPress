<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\ScanItems as ScanItemsDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Services\Services;

class CompleteQueue {

	use ModConsumer;

	public function complete() {
		$con = $this->getCon();
		/** @var ModCon $mod */
		$mod = $this->getMod();

		/** @var ScanItemsDB\Ops\Delete $deleter */
		$deleter = $mod->getDbH_ScanItems()->getQueryDeleter();
		$deleter->filterByFinished()->query();

//		foreach ( $dbhQ->getQuerySelector()->getDistinctForColumn( 'scan' ) as $scanSlug ) {
//
//			$scanCon = $mod->getScanCon( $scanSlug );
//
//			$resultsSet = ( new CollateResults() )
//				->setScanController( $scanCon )
//				->setDbHandler( $dbhQ )
//				->collate( $scanSlug );
//
//			$con->fireEvent( 'scan_run', [ 'audit_params' => [ 'scan' => $scanCon->getScanName() ] ] );
//
//			( new HackGuard\Scan\Results\ResultsUpdate() )
//				->setScanController( $scanCon )
//				->update( $resultsSet );
//
//			if ( $resultsSet->countItems() > 0 ) {
//
//				$items = $resultsSet->countItems() > 30 ?
//					__( 'Only the first 30 items are shown.', 'wp-simple-firewall' )
//					: __( 'The following items were discovered.', 'wp-simple-firewall' );
//
//				$itemDescriptions = array_slice( array_unique( array_map( function ( $item ) {
//					return $item->getDescriptionForAudit();
//				}, $resultsSet->getItems() ) ), 0, 30 );
//				$items .= ' "'.implode( '", "', $itemDescriptions ).'"';
//
//				$con->fireEvent(
//					'scan_items_found',
//					[
//						'audit_params' => [
//							'scan'  => $scanCon->getScanName(),
//							'items' => $items
//						]
//					]
//				);
//			}
//		}

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
