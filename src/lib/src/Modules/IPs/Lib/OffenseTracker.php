<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Events\Lib\EventsListener;

class OffenseTracker extends EventsListener {

	/**
	 * @var bool
	 */
	private $bIsBlocked = false;

	/**
	 * @var int
	 */
	private $nOffenseCount = 0;

	/**
	 * @param string $evt
	 * @param array  $aMeta
	 */
	protected function captureEvent( $evt, $aMeta = [] ) {
		$aDef = $this->getCon()
					 ->loadEventsService()
					 ->getEventDef( $evt );

		if ( !empty( $aDef ) && !empty( $aDef[ 'offense' ] ) && empty( $aMeta[ 'suppress_offense' ] ) ) {
			$this->incrementCount( isset( $aMeta[ 'offense_count' ] ) ? $aMeta[ 'offense_count' ] : 1 );
			if ( !empty( $aMeta[ 'block' ] ) ) {
				$this->setIsBlocked( true );
			}
		}
	}

	/**
	 * @return bool
	 */
	public function hasVisitorOffended() {
		return $this->isBlocked() || $this->getOffenseCount() > 0;
	}

	/**
	 * @return bool
	 */
	public function isBlocked() {
		return (bool)$this->bIsBlocked;
	}

	/**
	 * @return int
	 */
	public function getOffenseCount() {
		return (int)$this->nOffenseCount;
	}

	/**
	 * @param bool $bIsBlocked
	 * @return $this
	 */
	public function setIsBlocked( $bIsBlocked ) {
		$this->bIsBlocked = $bIsBlocked;
		return $this;
	}

	/**
	 * @param int $nIncrement
	 * @return $this
	 */
	public function incrementCount( $nIncrement = 1 ) {
		return $this->setOffenseCount( $this->getOffenseCount() + (int)$nIncrement );
	}

	/**
	 * @param int $nOffenseCount
	 * @return $this
	 */
	public function setOffenseCount( $nOffenseCount ) {
		$this->nOffenseCount = max( $nOffenseCount, (int)$this->nOffenseCount );
		return $this;
	}
}