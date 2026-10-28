<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Events\EventsListener;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Standing\{
	EvidenceLedger,
	EvidenceWeights
};

class OffenseTracker extends EventsListener {

	private bool $isBlocked = false;

	private int $offenseCount = 0;

	private EvidenceLedger $evidence;

	protected function captureEvent( string $evt, array $meta = [], array $def = [] ) {
		if ( !empty( $def[ 'offense' ] ) && empty( $meta[ 'suppress_offense' ] ) ) {
			$count = (int)( $meta[ 'offense_count' ] ?? 1 );
			$this->incrementCount( $count );
			if ( !empty( $meta[ 'block' ] ) ) {
				$this->setIsBlocked( true );
			}
			// An offense counted as zero is only being logged, so it isn't evidence either.
			if ( $count > 0 ) {
				( new EvidenceWeights() )->addEvent( $this->getEvidence(), $evt, $meta, $def );
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

	public function getEvidence() :EvidenceLedger {
		return $this->evidence ??= new EvidenceLedger();
	}

	public function setIsBlocked( bool $isBlocked ) {
		$this->isBlocked = $isBlocked;
	}

	public function incrementCount( int $increment = 1 ) {
		$this->setOffenseCount( $this->getOffenseCount() + $increment );
	}

	/**
	 * Offenses added by a custom rule response, which have no event of their own.
	 */
	public function addCustomRuleOffenses( int $count ) :void {
		$this->incrementCount( $count );
		( new EvidenceWeights() )->addCustomRuleOffenses( $this->getEvidence(), $count );
	}

	public function setOffenseCount( int $offenseCount ) {
		$this->offenseCount = (int)\max( $offenseCount, $this->getOffenseCount() );
	}
}