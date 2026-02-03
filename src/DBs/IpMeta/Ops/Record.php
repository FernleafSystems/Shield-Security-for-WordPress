<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\IpMeta\Ops;

/**
 * @property int    $ip_ref
 * @property int    $asn
 * @property string $country_iso2
 * @property bool   $pc_is_proxy
 * @property int    $geo_updated_at
 * @property int    $pc_last_check_at
 * @property int    $updated_at
 */
class Record extends \FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Record {

	public function __get( string $key ) {
		$value = parent::__get( $key );

		if ( $key === 'ip_ref' ) {
			$value = (int)$value;
		}
		elseif ( $key === 'pc_is_proxy' ) {
			$value = (bool)$value;
		}

		return $value;
	}
}