<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

/**
 * TODO: not the most efficient
 */
class SetScanCompleted {

	use ModConsumer;

	public function run() {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		foreach ( ( new ScansStatus() )->setMod( $mod )->enqueued() as $scan ) {
			$count = (int)Services::WpDb()->getVar(
				sprintf( "SELECT count(*)
						FROM `%s` as scans
						INNER JOIN `%s` as `si`
							ON `si`.scan_ref = `scans`.id
							AND `si`.finished_at=0
						WHERE `scans`.`scan`='%s'
						  AND `scans`.`ready_at` > 0
						  AND `scans`.`finished_at`=0;",
					$mod->getDbH_Scans()->getTableSchema()->table,
					$mod->getDbH_ScanItems()->getTableSchema()->table,
					$scan
				)
			);
			if ( $count === 0 ) {
				$mod->getDbH_Scans()
					->getQueryUpdater()
					->setUpdateWheres( [
						'scan'        => $scan,
						'finished_at' => 0,
					] )
					->setUpdateData( [
						'finished_at' => Services::Request()->ts()
					] )
					->query();

				$scanCon = $mod->getScanCon( $scan );
				$this->getCon()
					 ->fireEvent( 'scan_run',
						 [
							 'audit_params' => [
								 'scan' => $scanCon->getScanName()
							 ]
						 ]
					 );

				$this->auditLatestScanItems( $scanCon );
			}
		}
	}

	/**
	 * @param Base $scanCon
	 */
	private function auditLatestScanItems( $scanCon ) {
		$results = $scanCon->getResultsForDisplay();

		if ( $results->countItems() > 0 ) {

			$items = $results->countItems() > 30 ?
				__( 'Only the first 30 items are shown.', 'wp-simple-firewall' )
				: __( 'The following items were discovered.', 'wp-simple-firewall' );

			$itemDescriptions = array_slice( array_unique( array_map( function ( $item ) {
				return $item->getDescriptionForAudit();
			}, $results->getItems() ) ), 0, 30 );

			$items .= ' "'.implode( '", "', $itemDescriptions ).'"';

			$this->getCon()
				 ->fireEvent(
					 'scan_items_found',
					 [
						 'audit_params' => [
							 'scan'  => $scanCon->getScanName(),
							 'items' => $items
						 ]
					 ]
				 );
		}
	}
}
