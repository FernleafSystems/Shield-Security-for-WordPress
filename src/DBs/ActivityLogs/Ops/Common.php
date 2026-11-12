<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\ActivityLogs\Ops;

trait Common {

	public function filterByEvent( string $event ) {
		return $this->addWhereEquals( 'event_slug', $event );
	}
}