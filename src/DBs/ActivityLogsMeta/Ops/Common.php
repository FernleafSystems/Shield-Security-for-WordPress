<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\ActivityLogsMeta\Ops;

trait Common {

	public function filterByMetaKey( string $key ) {
		return $this->addWhereEquals( 'meta_key', $key );
	}

	public function filterByLogRefs( array $logRefs ) {
		return $this->addWhereIn( 'log_ref', \array_map( '\intval', $logRefs ) );
	}
}