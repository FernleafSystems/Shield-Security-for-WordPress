<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Process;

use FernleafSystems\Wordpress\Plugin\Core\Rest\Exceptions\ApiException;

class ScanResults extends ScanBase {

	protected function process() :array {

		if ( $this->getScansStatus()[ 'enqueued_count' ] > 0 ) {
			throw new ApiException( 'Results are unavailable while scans are currently running.' );
		}

		$statesToInclude = $this->getWpRestRequest()->get_param( 'filter_item_state' );
		if ( \is_string( $statesToInclude ) ) {
			$statesToInclude = \array_filter( \explode( ',', $statesToInclude ) );
		}

		$results = [];
		foreach ( $this->getWpRestRequest()->get_param( 'scan_slugs' ) as $scanSlug ) {
			$RS = self::con()
				->comps
				->scans
				->getScanCon( $scanSlug )
				->getAllResults();

			$thisResults = [];

			foreach ( $RS->getAllItems() as $item ) {
				$item = \array_merge(
					$item->getRawData(),
					\array_intersect_key(
						$item->VO->getRawData(),
						\array_flip( [
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
					\ksort( $item );
					$thisResults[] = $item;
				}
			}

			$results[ $scanSlug ] = [
				'total' => \count( $thisResults ),
				'items' => $thisResults,
			];
		}

		\ksort( $results );
		return $results;
	}
}