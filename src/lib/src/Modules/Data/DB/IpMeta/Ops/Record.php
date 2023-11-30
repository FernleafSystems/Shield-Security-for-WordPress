<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\IpMeta\Ops;

/**
 * @property int $ip_ref
 * @property int $updated_at
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