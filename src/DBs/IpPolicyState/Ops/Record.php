<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\IpPolicyState\Ops;

/**
 * @property int    $ip_ref
 * @property string $risk_band
 * @property int    $risk_score
 * @property int    $last_evidence_at
 * @property int    $last_decision_at
 * @property int    $expires_at
 * @property array  $meta
 * @property int    $updated_at
 */
class Record extends \FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Record {

	public function __get( string $key ) {
		$value = parent::__get( $key );

		if ( \in_array( $key, [ 'ip_ref', 'risk_score', 'last_evidence_at', 'last_decision_at', 'expires_at', 'updated_at' ], true ) ) {
			$value = (int)$value;
		}
		elseif ( $key === 'risk_band' ) {
			$value = (string)$value;
		}
		elseif ( $key === 'meta' && !\is_array( $value ) ) {
			$value = [];
		}

		return $value;
	}
}
