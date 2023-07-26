<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results;

use FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Record;

/**
 * @property int    $scan_id
 * @property string $scan
 * @property string $hash
 * @property int    $resultitem_id
 * @property int    $scanresult_id
 * @property string $item_type
 * @property string $item_id
 * @property array  $meta
 * @property int    $att
 * @property int    $ignored_at
 * @property int    $notified_at
 * @property int    $auto_filtered_at
 * @property int    $attempt_repair_at
 * @property int    $item_repaired_at
 * @property int    $item_deleted_at
 * @property int    $created_at
 */
class ScanResultVO extends Record {

	/**
	 * @return mixed
	 */
	public function __get( string $key ) {

		$value = parent::__get( $key );

		switch ( $key ) {
			case 'scan_id':
			case 'resultitem_id':
			case 'scanresult_id':
				$value = (int)$value;
				break;
			default:
				break;
		}

		return $value;
	}
}
