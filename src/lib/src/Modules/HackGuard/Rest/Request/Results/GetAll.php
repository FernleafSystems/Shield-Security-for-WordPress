<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Rest\Request\Results;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Rest\Request\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Rest\Request\RequestVO;

class GetAll extends Base {

	protected function process() :array {
		/** @var RequestVO $req */
		$req = $this->getRequestVO();

		if ( $this->getScansStatus()[ 'enqueued_count' ] > 0 ) {
			throw new \Exception( 'Results are unavailable while scans are currently running.' );
		}

		$statesToInclude = [];
		if ( !empty( $req->filter_item_state ) ) {
			$statesToInclude = array_filter( explode( ',', $req->filter_item_state ) );
		}

		$results = [];
		foreach ( $req->scan_slugs as $scanSlug ) {
			$RS = $this->con()
					   ->getModule_HackGuard()
					   ->getScansCon()
					   ->getScanCon( $scanSlug )
					   ->getAllResults();
			$thisResults = [];
			foreach ( $RS->getAllItems() as $item ) {
				$item = array_merge(
					$item->getRawData(),
					array_intersect_key(
						$item->VO->getRawData(),
						array_flip( [
							'ignored_at',
							'notified_at',
							'attempt_repair_at',
							'item_repaired_at',
							'item_deleted_at',
							'item_id',
							'item_type'
						] )
					)
				);

				// TODO: we ought to filter some of these at the DB query stage
				if ( empty( $statesToInclude ) ) {
					$include = true;
				}
				else {
					$include = false;
					foreach ( $statesToInclude as $itemState ) {
						if ( !empty( $item[ $itemState ] ) ) {
							$include = true;
							break;
						}
					}
				}

				if ( $include ) {
					ksort( $item );
					$thisResults[] = $item;
				}
			}

			$results[ $scanSlug ] = [
				'total' => count( $thisResults ),
				'items' => $thisResults,
			];
		}

		ksort( $results );
		return $results;
	}
}