<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\Event\Ops;

trait Common {

	/**
	 * @return $this
	 */
	public function filterByEvent( string $event ) {
		return $this->filterByEvents( [ $event ] );
	}

	/**
	 * @param string[] $events
	 * @return $this
	 */
	public function filterByEvents( array $events ) {
		return $this->addWhereIn( 'event', $events );
	}
}