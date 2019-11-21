<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Events;

/**
 * Trait Filters
 * @package FernleafSystems\Wordpress\Plugin\Shield\Databases\Events
 */
trait Common {

	/**
	 * @param string $sEvent
	 * @return $this
	 */
	public function filterByEvent( $sEvent ) {
		return $this->filterByEvents( [ $sEvent ] );
	}

	/**
	 * @param string[] $aEvents
	 * @return $this
	 */
	public function filterByEvents( $aEvents ) {
		return $this->addWhereIn( 'event', $aEvents );
	}
}