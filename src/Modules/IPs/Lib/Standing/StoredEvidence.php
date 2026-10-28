<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Standing;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\Ops\Record;

/**
 * The evidence held on an IP's auto-block record, faded to a point in time.
 *
 * A record from before visitor standing has offenses but no evidence written yet. Its offenses count as
 * normal evidence from its last access, and if it's blocked it's held as hostile for the rest of its block.
 * The first evidence written to it carries the offenses over the same way (see Update::addEvidence()).
 */
class StoredEvidence {

	public const META_WEAK = 'evidence_weak';

	private Record $record;

	public function __construct( Record $record ) {
		$this->record = $record;
	}

	public function isLegacy() :bool {
		return $this->record->evidence_at === 0 && $this->record->offenses > 0;
	}

	public function offensesFor( string $strength, int $now ) :float {
		$record = $this->record;
		if ( $strength === EvidenceWeights::WEAK ) {
			$offenses = (float)\array_sum( $this->weakSignals( $now ) );
		}
		elseif ( $this->isLegacy() ) {
			$offenses = $strength === EvidenceWeights::NORMAL
				? EvidenceWeights::Fade( (float)$record->offenses, $record->last_access_at, $now, $strength )
				: 0.0;
		}
		else {
			$offenses = EvidenceWeights::Fade(
				$strength === EvidenceWeights::NORMAL ? $record->normal_evidence : $record->strong_evidence,
				$record->evidence_at,
				$now,
				$strength
			);
		}
		return $offenses;
	}

	/**
	 * @return array<string, float> faded weak offenses per signal
	 */
	public function weakSignals( int $now ) :array {
		return \array_filter( \array_map(
			fn( $offenses ) => EvidenceWeights::Fade( (float)$offenses, $this->record->evidence_at, $now, EvidenceWeights::WEAK ),
			(array)( $this->record->meta[ self::META_WEAK ] ?? [] )
		) );
	}

	public function total( int $now ) :float {
		return $this->offensesFor( EvidenceWeights::NORMAL, $now )
			   + $this->offensesFor( EvidenceWeights::STRONG, $now )
			   + $this->offensesFor( EvidenceWeights::WEAK, $now );
	}

	/**
	 * @param int $blockSeconds the auto-block expiry, for records from before visitor standing
	 */
	public function hostileUntil( int $blockSeconds ) :int {
		$record = $this->record;
		if ( $this->isLegacy() ) {
			$until = ( $record->blocked_at > 0 && $record->blocked_at >= $record->unblocked_at )
				? $record->last_access_at + $blockSeconds
				: 0;
		}
		else {
			$until = $record->hostile_until;
		}
		return $until;
	}
}
