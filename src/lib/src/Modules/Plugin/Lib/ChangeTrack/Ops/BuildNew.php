<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ChangeTrack\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\DB\Changes\Ops as ChangesDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ChangeTrack\SnapshotVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class BuildNew {

	use ModConsumer;

	/**
	 * @throws \Exception
	 */
	public function scheduled() :SnapshotVO {
		/** @var ChangesDB\Select $select */
		$select = $this->mod()
					   ->getChangeTrackCon()
					   ->getDbH_Changes()
					   ->getQuerySelector();

		$carbon = Services::Request()->carbon( true );
		/** @var ?ChangesDB\Record $latestFull */
		$latestFull = $select->filterIsFull()
							 ->setOrderBy( 'created_at', 'DESC', true )
							 ->first();
		$fullRequired = apply_filters( 'shield/change_tracking_is_scheduled_full_snapshot_required',
			empty( $latestFull ) || ( $this->isFullSnapshotDay() && $latestFull->created_at < $carbon->startOfDay()->timestamp )
		);

		return $fullRequired ? ( new BuildNew() )->full() : ( new BuildNew() )->diff();
	}

	/**
	 * @throws \Exception
	 */
	public function full() :SnapshotVO {
		return ( new CollateSnapshots() )->run();
	}

	/**
	 * @throws \Exception
	 */
	public function diff( ?SnapshotVO $baseFull = null ) :SnapshotVO {
		if ( empty( $baseFull ) ) {
			$baseFull = ( new RetrieveSnapshot() )->latest();
		}
		return ( new Diff( $baseFull, $this->full() ) )->run();
	}

	private function isFullSnapshotDay() :bool {
		return apply_filters( 'shield/change_tracking_is_scheduled_full_snapshot_day',
			Services::Request()->carbon( true )->isMonday() );
	}
}