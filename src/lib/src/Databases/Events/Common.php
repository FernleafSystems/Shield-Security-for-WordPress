<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Events;

trait Common {

	/**
	 * @param string $event
	 * @return $this
	 */
	public function filterByEvent( $event ) {
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