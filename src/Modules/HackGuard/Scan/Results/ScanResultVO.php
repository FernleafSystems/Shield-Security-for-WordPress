<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results;

use FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Record;

/**
 * @property int    $scan_id
 * @property string $scan
 * @property int    $resultitem_id
 * @property string $item_type
 * @property string $item_id
 * @property string $asset_type
 * @property string $asset_key
 * @property array  $meta
 * @property int    $ignored_at
 * @property int    $notified_at
 * @property int    $auto_filtered_at
 * @property int    $attempt_repair_at
 * @property int    $last_seen_at
 * @property int    $resolved_at
 * @property string $resolution_reason
 * @property int    $created_at
 */
class ScanResultVO extends Record {

	public function __get( string $key ) {
		$value = parent::__get( $key );

		if ( \in_array( $key, [ 'scan_id', 'resultitem_id' ], true ) ) {
			$value = (int)$value;
		}

		return $value;
	}

	public function isResolvedAs( string $reason ) :bool {
		return (int)$this->resolved_at > 0 && $this->resolution_reason === $reason;
	}

	public function isDeleted() :bool {
		return $this->isResolvedAs( 'deleted' );
	}

	public function isRepaired() :bool {
		return $this->isResolvedAs( 'repaired' );
	}
}
