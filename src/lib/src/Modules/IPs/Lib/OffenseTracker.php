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
	 * @param string $sEvent
	 * @param array  $aMeta
	 */
	protected function captureEvent( $sEvent, $aMeta = [] ) {
		$aDef = $this->getCon()
					 ->loadEventsService()
					 ->getEventDef( $sEvent );
		if ( !empty( $aDef )
			 && $aDef[ 'offense' ] && empty( $aMeta[ 'suppress_offense' ] ) ) {
			$this->setOffenseCount(
				isset( $aMeta[ 'offense_count' ] ) ? $aMeta[ 'offense_count' ] : 1
			);
		}
	}

	/**
	 * @return bool
	 */
	public function hasVisitorOffended() {
		return $this->getOffenseCount() > 0;
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
	 * @param int $nOffenseCount
	 * @return $this
	 */
	public function setOffenseCount( $nOffenseCount ) {
		$this->nOffenseCount = max( $nOffenseCount, (int)$this->nOffenseCount );
		return $this;
	}
}