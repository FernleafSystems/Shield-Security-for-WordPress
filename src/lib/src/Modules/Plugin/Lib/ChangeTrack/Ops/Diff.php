<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ChangeTrack\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ChangeTrack\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ChangeTrack\Hasher;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ChangeTrack\SnapshotVO;

class Diff {

	private $old;

	private $new;

	public function __construct( SnapshotVO $old, SnapshotVO $new ) {
		$this->old = $old;
		$this->new = $new;
	}

	public function run() :SnapshotVO {
		$snapshot = new SnapshotVO();
		$snapshot->data = [
			Constants::DIFF_TYPE_ADDED   => $this->added(),
			Constants::DIFF_TYPE_REMOVED => $this->removed(),
			Constants::DIFF_TYPE_CHANGED => $this->changed(),
		];
		$snapshot->is_diff = true;
		$snapshot->snapshot_at = $this->new->snapshot_at;
		return $snapshot;
	}

	protected function added() :array {
		$items = [];
		foreach ( $this->new->data as $snapsKey => $snapshot ) {
			if ( !\hash_equals( Hasher::Snap( $snapshot ), Hasher::Snap( $this->old->data[ $snapsKey ] ?? [] ) ) ) {
				// We array filter later in-case it's empty.
				$items[ $snapsKey ] = \array_diff_key( $snapshot, $this->old->data[ $snapsKey ] ?? [] );
			}
		}
		return \array_filter( $items );
	}

	protected function removed() :array {
		$items = [];
		foreach ( $this->old->data as $snapsKey => $snapshot ) {
			if ( !\hash_equals( Hasher::Snap( $snapshot ), Hasher::Snap( $this->new->data[ $snapsKey ] ?? [] ) ) ) {
				// We array filter later in-case it's empty.
				$items[ $snapsKey ] = \array_diff_key( $snapshot, $this->new->data[ $snapsKey ] ?? [] );
			}
		}
		return \array_filter( $items );
	}

	protected function changed() :array {
		$items = [];
		foreach ( $this->old->data as $snapsKey => $snapshot ) {
			$items[ $snapsKey ] = [];
			$commonSnaps = \array_intersect_key( $snapshot, $this->new->data[ $snapsKey ] );
			foreach ( $commonSnaps as $snapKey => $commonSnap ) {
				if ( !\hash_equals( Hasher::Snap( $commonSnap ), Hasher::Snap( $this->new->data[ $snapsKey ][ $snapKey ] ) ) ) {
					$items[ $snapsKey ][ $snapKey ] = [
						'old' => $commonSnap,
						'new' => $this->new->data[ $snapsKey ][ $snapKey ],
					];
				}
			}
		}
		return \array_filter( $items );
	}
}