<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\{
	DiffVO,
	Hasher,
	SnapshotVO
};

class Diff {

	private $old;

	private $new;

	public function __construct( SnapshotVO $old, SnapshotVO $new ) {
		$this->old = $old;
		$this->new = $new;
	}

	public function run() :DiffVO {
		$diff = new DiffVO();
		$diff->added = $this->added();
		$diff->removed = $this->removed();
		$diff->changed = $this->changed();
		return $diff;
	}

	protected function added() :array {
		return \hash_equals( Hasher::Snap( $this->new->data ), Hasher::Snap( $this->old->data ) ) ? []
			: \array_diff_key( $this->new->data, $this->old->data );
	}

	protected function removed() :array {
		return \hash_equals( Hasher::Snap( $this->new->data ), Hasher::Snap( $this->old->data ) ) ? []
			: \array_diff_key( $this->old->data, $this->new->data );
	}

	protected function changed() :array {
		$items = [];
		foreach ( \array_intersect_key( $this->old->data, $this->new->data ) as $snapKey => $commonSnap ) {
			if ( !\hash_equals( Hasher::Snap( $commonSnap ), Hasher::Snap( $this->new->data[ $snapKey ] ) ) ) {
				$items[ $snapKey ] = [
					'old' => $commonSnap,
					'new' => $this->new->data[ $snapKey ],
				];
			}
		}
		return \array_filter( $items );
	}
}