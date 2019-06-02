<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Events;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

class Select extends Base\Select {

	/**
	 * @param int $nGreaterThan
	 * @return $this
	 */
	public function filterByCountGreaterThan( $nGreaterThan ) {
		return $this->addWhere( 'count', (int)$nGreaterThan, '>' );
	}

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