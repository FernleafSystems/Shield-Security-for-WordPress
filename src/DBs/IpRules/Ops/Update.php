<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\IpRuleRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Standing\{
	EvidenceWeights,
	StoredEvidence
};
use FernleafSystems\Wordpress\Services\Services;

class Update extends \FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Update {

	/**
	 * @param Record|IpRuleRecord $IP
	 */
	public function incrementTransgressions( $IP, int $increment = 1 ) :bool {
		return $this->updateTransgressions( $IP, $IP->offenses + $increment );
	}

	/**
	 * @param Record|IpRuleRecord $IP
	 */
	public function updateTransgressions( $IP, int $offenses, bool $updateLastAccess = true ) :bool {
		$data = [
			'offenses' => \max( 0, $offenses ),
		];
		if ( $updateLastAccess ) {
			$data[ 'last_access_at' ] = Services::Request()->ts();
		}
		return $this->updateRecord( $IP, $data );
	}

	public function updateLabel( Record $IP, string $label ) :bool {
		return $this->updateRecord( $IP, [ 'label' => \trim( $label ) ] );
	}

	/**
	 * @param Record|IpRuleRecord $record
	 */
	public function updateLastAccessAt( $record ) :bool {
		return $this->updateById( $record->id, [ 'last_access_at' => Services::Request()->ts() ] );
	}

	public function setBlocked( Record $IP ) :bool {
		return $this->updateRecord( $IP, [
			'blocked_at'     => Services::Request()->ts(),
			'last_access_at' => Services::Request()->ts()
		] );
	}

	/**
	 * Adds one request's offenses and evidence to the record in a single statement, so parallel requests from
	 * one IP lose neither. Every expression reads only the row's old values: columns are only read before
	 * they're assigned, so the result is the same whether the database applies assignments in order (MySQL)
	 * or simultaneously (MariaDB's SIMULTANEOUS_ASSIGNMENT). Elapsed time is clamped at zero and timestamps
	 * never move back, so a slower request writing after a newer one adds its evidence unfaded. A record from
	 * before visitor standing carries its offenses over as normal evidence, and its implied hold, on this first
	 * write (see StoredEvidence). New evidence renews a running hold.
	 * @param array<string, float> $weakSignals faded and capped weak offenses per signal, as of $now
	 * @param float                $hostileAt   0 when offense-based hostility is off
	 */
	public function addOffenseEvidence(
		Record $record,
		int $offenses,
		float $normal,
		float $strong,
		array $weakSignals,
		float $hostileAt,
		int $holdSeconds,
		int $now
	) :bool {
		global $wpdb;
		$fadedNormal = $this->fadedEvidenceSql( EvidenceWeights::NORMAL, $now );
		$fadedStrong = $this->fadedEvidenceSql( EvidenceWeights::STRONG, $now );
		$hold = $this->effectiveHoldSql( $holdSeconds );

		$meta = $record->meta;
		$meta[ StoredEvidence::META_WEAK ] = $weakSignals;

		$updated = $wpdb->query( $wpdb->prepare(
			"UPDATE `{$this->getDbH()->getTable()}`
			SET `hostile_until` = IF(
					%f > 0 AND ( {$fadedNormal} + {$fadedStrong} + %f >= %f OR {$hold} > %d ),
					GREATEST( {$hold}, %d ),
					{$hold}
				),
				`normal_evidence` = {$fadedNormal} + %f,
				`strong_evidence` = {$fadedStrong} + %f,
				`meta` = %s,
				`offenses` = `offenses` + %d,
				`last_access_at` = GREATEST( `last_access_at`, %d ),
				`evidence_at` = GREATEST( `evidence_at`, %d ),
				`updated_at` = %d
			WHERE `id` = %d",
			$hostileAt,
			$normal + $strong + (float)\array_sum( $weakSignals ),
			$hostileAt,
			$now,
			$now + $holdSeconds,
			$normal,
			$strong,
			(string)$record->arrayDataWrap( $meta ),
			\max( 0, $offenses ),
			$now,
			$now,
			$now,
			$record->id
		) );

		return !empty( $updated );
	}

	/**
	 * Adds evidence and offenses taken from merged duplicate records, keeping the latest hold, and blocks the IP
	 * if its offenses now reach the limit. Merging moves evidence that already exists, so it doesn't renew the
	 * hold. As with addOffenseEvidence(), columns are only read before they're assigned.
	 * @param array<string, float> $weakSignals merged and capped weak offenses per signal, as of $now
	 */
	public function addMergedEvidence(
		Record $record,
		int $offenses,
		float $normal,
		float $strong,
		array $weakSignals,
		int $hostileUntil,
		int $offenseLimit,
		int $holdSeconds,
		int $now
	) :bool {
		global $wpdb;
		$meta = $record->meta;
		$meta[ StoredEvidence::META_WEAK ] = $weakSignals;

		$updated = $wpdb->query( $wpdb->prepare(
			"UPDATE `{$this->getDbH()->getTable()}`
			SET `hostile_until` = GREATEST( {$this->effectiveHoldSql( $holdSeconds )}, %d ),
				`blocked_at` = IF( `offenses` + %d >= %d AND `blocked_at` <= `unblocked_at`, %d, `blocked_at` ),
				`normal_evidence` = {$this->fadedEvidenceSql( EvidenceWeights::NORMAL, $now )} + %f,
				`strong_evidence` = {$this->fadedEvidenceSql( EvidenceWeights::STRONG, $now )} + %f,
				`meta` = %s,
				`offenses` = `offenses` + %d,
				`evidence_at` = GREATEST( `evidence_at`, %d ),
				`updated_at` = %d
			WHERE `id` = %d",
			$hostileUntil,
			\max( 0, $offenses ),
			$offenseLimit,
			$now,
			$normal,
			$strong,
			(string)$record->arrayDataWrap( $meta ),
			\max( 0, $offenses ),
			$now,
			$now,
			$record->id
		) );

		return !empty( $updated );
	}

	/**
	 * The row's stored evidence faded to $now, carrying offenses over for a record from before visitor standing.
	 * Approximate-value literals make MySQL divide in floating point rather than round to 4 decimal places.
	 */
	private function fadedEvidenceSql( string $strength, int $now ) :string {
		$halfLife = \sprintf( '%dE0', EvidenceWeights::HALF_LIFE[ $strength ] );
		$sinceEvidence = \sprintf( '( GREATEST( %d, `evidence_at` ) - `evidence_at` )', $now );
		$sinceAccess = \sprintf( '( GREATEST( %d, `last_access_at` ) - `last_access_at` )', $now );
		return $strength === EvidenceWeights::NORMAL
			? "IF( `evidence_at` = 0, `offenses`*POW( 0.5, {$sinceAccess}/{$halfLife} ), `normal_evidence`*POW( 0.5, {$sinceEvidence}/{$halfLife} ) )"
			: "IF( `evidence_at` = 0, 0, `strong_evidence`*POW( 0.5, {$sinceEvidence}/{$halfLife} ) )";
	}

	/**
	 * The row's hold, including the one implied for a blocked record from before visitor standing.
	 */
	private function effectiveHoldSql( int $holdSeconds ) :string {
		return \sprintf(
			'IF( `evidence_at` = 0 AND `blocked_at` > 0 AND `blocked_at` >= `unblocked_at`, `last_access_at` + %d, `hostile_until` )',
			$holdSeconds
		);
	}
}
