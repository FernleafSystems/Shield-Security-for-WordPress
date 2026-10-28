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
	 * Fades the stored normal and strong evidence and adds to it in one statement, so evidence from
	 * parallel requests isn't lost. MySQL applies the assignments in order, so the hold check sees the
	 * new totals and `evidence_at` must be set last.
	 * @param array<string, int> $weakSignals faded and capped weak units per signal, as of now
	 * @param int                $hostileAtUnits 0 when offense-based hostility is off
	 */
	public function addEvidence( Record $record, int $normal, int $strong, array $weakSignals, int $hostileAtUnits, int $holdUntil ) :bool {
		global $wpdb;
		$now = Services::Request()->ts();
		$table = $this->getDbH()->getTable();

		$meta = $record->meta;
		$meta[ StoredEvidence::META_WEAK ] = $weakSignals;

		$updated = $wpdb->query( $wpdb->prepare(
			"UPDATE `{$table}`
			SET `evidence_normal` = FLOOR( `evidence_normal`*POW( 0.5, GREATEST( 0, %d - `evidence_at` )/%d ) ) + %d,
				`evidence_strong` = FLOOR( `evidence_strong`*POW( 0.5, GREATEST( 0, %d - `evidence_at` )/%d ) ) + %d,
				`hostile_until` = IF( %d > 0 AND `evidence_normal` + `evidence_strong` + %d >= %d, GREATEST( `hostile_until`, %d ), `hostile_until` ),
				`meta` = %s,
				`evidence_at` = %d,
				`updated_at` = %d
			WHERE `id` = %d",
			$now,
			EvidenceWeights::HALF_LIFE[ EvidenceWeights::NORMAL ],
			$normal,
			$now,
			EvidenceWeights::HALF_LIFE[ EvidenceWeights::STRONG ],
			$strong,
			$hostileAtUnits,
			(int)\array_sum( $weakSignals ),
			$hostileAtUnits,
			$holdUntil,
			(string)$record->arrayDataWrap( $meta ),
			$now,
			$now,
			$record->id
		) );

		return $updated !== false;
	}
}