<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\Ops;

/**
 * @property int    $ip_ref
 * @property int    $cidr
 * @property bool   $is_range
 * @property int    $offenses
 * @property string $type
 * @property string $label
 * @property int    $last_access_at
 * @property int    $blocked_at
 * @property int    $unblocked_at
 * @property int    $last_unblock_attempt_at
 * @property bool   $can_export
 * @property int    $expires_at
 * @property int    $imported_at
 * @property int    $updated_at
 */
class Record extends \FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Record {

	public function __get( string $key ) {
		$value = parent::__get( $key );

		if ( \in_array( $key, [ 'label', 'type' ] ) ) {
			$value = (string)$value;
		}
		elseif ( \in_array( $key, [ 'ip_ref', 'cidr', 'offenses' ] ) ) {
			$value = (int)$value;
		}
		elseif ( \in_array( $key, [ 'is_range', 'can_export' ] ) ) {
			$value = (bool)$value;
		}

		if ( $key === 'label' && empty( $value ) ) {
			$value = '-no label-';
		}

		return $value;
	}
}