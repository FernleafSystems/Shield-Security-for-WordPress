<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Standing;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\Ops\Record;

/**
 * The evidence held on an IP's auto-block record, faded to a point in time.
 */
class StoredEvidence {

	public const META_WEAK = 'evidence_weak';

	private Record $record;

	public function __construct( Record $record ) {
		$this->record = $record;
	}

	public function unitsFor( string $strength, int $now ) :int {
		switch ( $strength ) {
			case EvidenceWeights::NORMAL:
				$units = $this->record->evidence_normal;
				break;
			case EvidenceWeights::STRONG:
				$units = $this->record->evidence_strong;
				break;
			default:
				$units = (int)\array_sum( $this->weakSignals( $now ) );
				break;
		}
		return $strength === EvidenceWeights::WEAK ? $units : EvidenceWeights::Fade( $units, $this->record->evidence_at, $now, $strength );
	}

	/**
	 * @return array<string, int> faded weak units per signal
	 */
	public function weakSignals( int $now ) :array {
		return \array_filter( \array_map(
			fn( $units ) => EvidenceWeights::Fade( (int)$units, $this->record->evidence_at, $now, EvidenceWeights::WEAK ),
			(array)( $this->record->meta[ self::META_WEAK ] ?? [] )
		) );
	}

	public function total( int $now ) :int {
		return $this->unitsFor( EvidenceWeights::NORMAL, $now )
			   + $this->unitsFor( EvidenceWeights::STRONG, $now )
			   + $this->unitsFor( EvidenceWeights::WEAK, $now );
	}
}
