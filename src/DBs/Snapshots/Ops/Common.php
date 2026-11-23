<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\Snapshots\Ops;

trait Common {

	public function filterBySlug( string $slug ) :self {
		return $this->addWhereEquals( 'slug', $slug );
	}
}