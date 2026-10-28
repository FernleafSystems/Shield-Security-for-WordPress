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
	 * Fades the stored normal and strong evidence and adds to it in one statement, so evidence from parallel
	 * requests isn't lost. A record from before visitor standing carries its offenses over as normal evidence
	 * on its first write (see StoredEvidence). MySQL and MariaDB apply the assignments in order, so the hold
	 * sees the new totals, and `evidence_at` must be set last. Elapsed time is clamped at zero, and
	 * `evidence_at` never moves back, so a slower request writing after a newer one adds its evidence unfaded.
	 * @param array<string, float> $weakSignals faded and capped weak offenses per signal, as of $now
	 * @param float                $hostileAt   0 when offense-based hostility is off
	 */
	public function addEvidence( Record $record, float $normal, float $strong, array $weakSignals, float $hostileAt, int $holdSeconds, int $now ) :bool {
		global $wpdb;
		$table = $this->getDbH()->getTable();

		$meta = $record->meta;
		$meta[ StoredEvidence::META_WEAK ] = $weakSignals;

		$sinceEvidence = \sprintf( '( GREATEST( %d, `evidence_at` ) - `evidence_at` )', $now );
		$sinceAccess = \sprintf( '( GREATEST( %d, `last_access_at` ) - `last_access_at` )', $now );
		// Approximate-value literals, so MySQL divides in floating point rather than rounding to 4 decimal places.
		$halfLifeNormal = \sprintf( '%dE0', EvidenceWeights::HALF_LIFE[ EvidenceWeights::NORMAL ] );
		$halfLifeStrong = \sprintf( '%dE0', EvidenceWeights::HALF_LIFE[ EvidenceWeights::STRONG ] );

		$updated = $wpdb->query( $wpdb->prepare(
			"UPDATE `{$table}`
			SET `normal_evidence` = IF( `evidence_at` = 0,
					`offenses`*POW( 0.5, {$sinceAccess}/{$halfLifeNormal} ),
					`normal_evidence`*POW( 0.5, {$sinceEvidence}/{$halfLifeNormal} )
				) + %f,
				`strong_evidence` = IF( `evidence_at` = 0, 0, `strong_evidence`*POW( 0.5, {$sinceEvidence}/{$halfLifeStrong} ) ) + %f,
				`hostile_until` = IF(
					%f > 0 AND (
						`normal_evidence` + `strong_evidence` + %f >= %f
						OR IF( `evidence_at` = 0 AND `blocked_at` > 0 AND `blocked_at` >= `unblocked_at`, `last_access_at` + %d, `hostile_until` ) > %d
					),
					GREATEST( `hostile_until`, %d ),
					`hostile_until`
				),
				`meta` = %s,
				`evidence_at` = GREATEST( `evidence_at`, %d ),
				`updated_at` = %d
			WHERE `id` = %d",
			$normal,
			$strong,
			$hostileAt,
			(float)\array_sum( $weakSignals ),
			$hostileAt,
			$holdSeconds,
			$now,
			$now + $holdSeconds,
			(string)$record->arrayDataWrap( $meta ),
			$now,
			$now,
			$record->id
		) );

		return $updated !== false;
	}
}
