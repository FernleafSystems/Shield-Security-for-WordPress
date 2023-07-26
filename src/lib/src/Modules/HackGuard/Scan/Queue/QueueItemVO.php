<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;

/**
 * @property int    $scan_id
 * @property int    $qitem_id
 * @property string $scan
 * @property array  $meta
 * @property array  $items
 */
class QueueItemVO extends DynPropertiesClass {

	public function __get( string $key ) {
		$value = parent::__get( $key );
		switch ( $key ) {
			case 'scan_id':
			case 'qitem_id':
				$value = (int)$value;
				break;
			case 'meta':
				if ( !\is_array( $value ) ) {
					$value = [];
				}
				break;
			default:
				break;
		}
		return $value;
	}
}
