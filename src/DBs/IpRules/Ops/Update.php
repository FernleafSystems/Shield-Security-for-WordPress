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
	 * one IP lose neither. Every expression reads only the row's old values, so the result does not depend on
	 * the order the database applies assignments in (MariaDB's SIMULTANEOUS_ASSIGNMENT included). Elapsed time
	 * is clamped at zero and timestamps never move back, so a slower request writing after a newer one adds its
	 * evidence unfaded. A record from before visitor standing carries its offenses over as normal evidence, and
	 * its implied hold, on this first write (see StoredEvidence). New evidence renews a running hold.
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
			SET `normal_evidence` = {$fadedNormal} + %f,
				`strong_evidence` = {$fadedStrong} + %f,
				`hostile_until` = IF(
					%f > 0 AND ( {$fadedNormal} + {$fadedStrong} + %f >= %f OR {$hold} > %d ),
					GREATEST( {$hold}, %d ),
					{$hold}
				),
				`meta` = %s,
				`offenses` = `offenses` + %d,
				`last_access_at` = GREATEST( `last_access_at`, %d ),
				`evidence_at` = GREATEST( `evidence_at`, %d ),
				`updated_at` = %d
			WHERE `id` = %d",
			$normal,
			$strong,
			$hostileAt,
			$normal + $strong + (float)\array_sum( $weakSignals ),
			$hostileAt,
			$now,
			$now + $holdSeconds,
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
	 * Adds evidence and offenses taken from merged duplicate records, keeping the latest hold. Merging moves
	 * evidence that already exists, so it doesn't renew the hold.
	 * @param array<string, float> $weakSignals merged and capped weak offenses per signal, as of $now
	 */
	public function addMergedEvidence(
		Record $record,
		int $offenses,
		float $normal,
		float $strong,
		array $weakSignals,
		int $hostileUntil,
		int $holdSeconds,
		int $now
	) :bool {
		global $wpdb;
		$hold = $this->effectiveHoldSql( $holdSeconds );

		$meta = $record->meta;
		$meta[ StoredEvidence::META_WEAK ] = $weakSignals;

		$updated = $wpdb->query( $wpdb->prepare(
			"UPDATE `{$this->getDbH()->getTable()}`
			SET `normal_evidence` = {$this->fadedEvidenceSql( EvidenceWeights::NORMAL, $now )} + %f,
				`strong_evidence` = {$this->fadedEvidenceSql( EvidenceWeights::STRONG, $now )} + %f,
				`hostile_until` = GREATEST( {$hold}, %d ),
				`meta` = %s,
				`offenses` = `offenses` + %d,
				`evidence_at` = GREATEST( `evidence_at`, %d ),
				`updated_at` = %d
			WHERE `id` = %d",
			$normal,
			$strong,
			$hostileUntil,
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
