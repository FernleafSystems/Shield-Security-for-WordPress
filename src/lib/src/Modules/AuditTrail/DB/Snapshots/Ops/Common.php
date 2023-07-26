<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\DB\Snapshots\Ops;

trait Common {

	public function filterIsDiff() :self {
		return $this->addWhereEquals( 'is_diff', 1 );
	}

	public function filterIsFull() :self {
		return $this->addWhereEquals( 'is_diff', 0 );
	}

	public function filterBySlug( string $slug ) :self {
		return $this->addWhereEquals( 'slug', $slug );
	}
}