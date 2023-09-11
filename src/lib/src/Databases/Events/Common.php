<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Events;

/**
 * @deprecated 18.3.1
 */
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