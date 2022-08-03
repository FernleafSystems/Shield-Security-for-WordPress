<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\Ops;

/**
 * @property int    $ip_ref
 * @property int    $cidr
 * @property int    $offenses
 * @property string $list
 * @property string $label
 * @property int    $last_access_at
 * @property int    $blocked_at
 */
class Record extends \FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Record {

	public function __get( string $key ) {
		$value = parent::__get( $key );

		if ( $key === 'ip_ref' ) {
			$value = (int)$value;
		}

		return $value;
	}
}