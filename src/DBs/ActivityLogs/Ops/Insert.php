<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\ActivityLogs\Ops;

use FernleafSystems\Wordpress\Services\Services;

class Insert extends \FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Insert {

	public function insertGetRecord( Record $record ) :?Record {
		$inserted = $this->insert( $record );
		if ( !$inserted ) {
			return null;
		}

		$wpdb = Services::WpDb()->loadWpdb();
		$id = (int)$wpdb->insert_id;
		if ( $id <= 0 ) {
			return null;
		}

		$record->id = $id;
		return $record;
	}
}
