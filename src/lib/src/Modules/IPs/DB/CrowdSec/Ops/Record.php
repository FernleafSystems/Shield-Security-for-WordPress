<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\CrowdSec\Ops;

/**
 * @property int $ip_ref
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