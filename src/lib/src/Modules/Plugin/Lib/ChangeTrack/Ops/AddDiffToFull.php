<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ChangeTrack\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ChangeTrack\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ChangeTrack\SnapshotVO;

class AddDiffToFull {

	private $full;

	private $fullData;

	private $diffSnap;

	public function __construct( SnapshotVO $full, SnapshotVO $diff ) {
		$this->full = $full;
		$this->fullData = $full->data;
		$this->diffSnap = $diff;
	}

	public function run() :void {
		$this->processAdded();
		$this->processRemoved();
		$this->processChanged();

		$this->full->data = $this->fullData;
		$this->full->snapshot_at = $this->diffSnap->snapshot_at;
	}

	private function processAdded() :void {
		foreach ( $this->diffSnap->data[ Constants::DIFF_TYPE_ADDED ] as $snapsKey => $snaps ) {
			$this->fullData[ $snapsKey ] = \array_merge( $this->fullData[ $snapsKey ], $snaps );
			\ksort( $this->fullData[ $snapsKey ] );
		}
	}

	private function processRemoved() :void {
		foreach ( $this->diffSnap->data[ Constants::DIFF_TYPE_REMOVED ] as $snapsKey => $snaps ) {
			$this->fullData[ $snapsKey ] = \array_diff_key( $this->fullData[ $snapsKey ], $snaps );
			\ksort( $this->fullData[ $snapsKey ] );
		}
	}

	private function processChanged() :void {
		foreach ( $this->diffSnap->data[ Constants::DIFF_TYPE_CHANGED ] as $snapsKey => $snaps ) {
			foreach ( $snaps as $snapKey => $changes ) {
				$this->fullData[ $snapsKey ][ $snapKey ] = $changes[ 'new' ];
			}
			\ksort( $this->fullData[ $snapsKey ] );
		}
	}
}