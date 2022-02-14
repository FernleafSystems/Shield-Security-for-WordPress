<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Rest\Request\Results;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Rest\Request\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Options;

class GetAll extends Base {

	protected function process() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var Options $opts */
		$opts = $this->getOptions();
		$req = $this->getRequestVO();

		if ( $this->getScansStatus()[ 'enqueued_count' ] > 0 ) {
			throw new \Exception( 'Results are unavailable while scans are currently running.' );
		}

		$scansToFilter = $opts->getScanSlugs();
		if ( !empty( $req->include_scan ) ) {
			$scansToFilter = array_intersect( $scansToFilter, explode( ',', $req->include_scan ) );
		}

		$statesToInclude = [];
		if ( !empty( $req->filter_item_state ) ) {
			$statesToInclude = array_filter( explode( ',', $req->filter_item_state ) );
		}

		$results = [];
		foreach ( $scansToFilter as $scanSlug ) {
			$RS = $mod->getScanCon( $scanSlug )->getAllResults();
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
							'scanresult_id'
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