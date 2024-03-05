<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\ActivityLogs\Ops;

trait Common {

	public function filterByEvent( string $event ) {
		return $this->addWhereEquals( 'event_slug', $event );
	}

	public function filterByRequestRef( int $reqRef ) {
		return $this->addWhereEquals( 'req_ref', $reqRef );
	}

	public function filterByRequestRefs( array $reqRef ) {
		return $this->addWhereIn( 'req_ref', $reqRef );
	}
}