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

	protected function captureEvent( string $evt, array $meta = [], array $def = [] ) {
		if ( !empty( $def[ 'offense' ] ) && empty( $meta[ 'suppress_offense' ] ) ) {
			$this->incrementCount( (int)( $meta[ 'offense_count' ] ?? 1 ) );
			if ( !empty( $meta[ 'block' ] ) ) {
				$this->setIsBlocked( true );
			}
		}
	}

	public function hasVisitorOffended() :bool {
		return $this->isBlocked() || $this->getOffenseCount() > 0;
	}

	public function isBlocked() :bool {
		return (bool)$this->bIsBlocked;
	}

	public function getOffenseCount() :int {
		return (int)$this->nOffenseCount;
	}

	/**
	 * @param bool $isBlocked
	 * @return $this
	 */
	public function setIsBlocked( bool $isBlocked ) {
		$this->bIsBlocked = $isBlocked;
		return $this;
	}

	/**
	 * @return $this
	 */
	public function incrementCount( int $increment = 1 ) {
		return $this->setOffenseCount( $this->getOffenseCount() + (int)$increment );
	}

	/**
	 * @return $this
	 */
	public function setOffenseCount( int $offenseCount ) {
		$this->nOffenseCount = max( $offenseCount, (int)$this->nOffenseCount );
		return $this;
	}
}