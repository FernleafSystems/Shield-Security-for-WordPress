<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ChangeTrack\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ChangeTrack\SnapshotVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class CollateSnapshots {

	use ModConsumer;

	/**
	 * @throws \Exception
	 */
	public function run() :SnapshotVO {
		$snapshot = new SnapshotVO();
		$snapshot->is_diff = false;
		$snapshot->data = \array_map(
			function ( $zone ) {
				$snaps = $zone->snap();
				foreach ( $snaps as &$snap ) {
					if ( !\is_array( $snap ) ) {
						throw new \Exception( sprintf( 'Snap must be an array: %s', var_export( $snap, true ) ) );
					}
					\ksort( $snap );
				}

				\ksort( $snaps );
				return $snaps;
			},
			$this->mod()->getChangeTrackCon()->getZones()
		);
		$snapshot->snapshot_at = Services::Request()->ts();

		return $snapshot;
	}
}
