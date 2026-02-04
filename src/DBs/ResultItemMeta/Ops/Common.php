<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\ResultItemMeta\Ops;

trait Common {

	public function filterByMetaKey( string $key ) {
		return $this->addWhereEquals( 'meta_key', $key );
	}

	public function filterByResultItemRef( int $ref ) {
		return $this->filterByResultItems( [ $ref ] );
	}

	public function filterByResultItems( array $refs ) {
		return $this->addWhereIn( 'ri_ref', \array_map( '\intval', $refs ) );
	}
}