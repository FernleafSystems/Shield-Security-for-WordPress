<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Events\EventsListener;

class OffenseTracker extends EventsListener {

	/**
	 * @var bool
	 */
	private $isBlocked = false;

	/**
	 * @var int
	 */
	private $offenseCount = 0;

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
		return $this->isBlocked;
	}

	public function getOffenseCount() :int {
		return $this->offenseCount;
	}

	public function setIsBlocked( bool $isBlocked ) {
		$this->isBlocked = $isBlocked;
	}

	public function incrementCount( int $increment = 1 ) {
		$this->setOffenseCount( $this->getOffenseCount() + $increment );
	}

	public function setOffenseCount( int $offenseCount ) {
		$this->offenseCount = (int)\max( $offenseCount, $this->getOffenseCount() );
	}
}