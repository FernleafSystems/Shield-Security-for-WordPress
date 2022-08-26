<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\IPs;

use FernleafSystems\Wordpress\Plugin\Shield;

/**
 * Used in version 16.0 to migrate the IP List to the newer IP Rules.
 * @deprecated 16.1
 */
class QueueReqDbRecordMigrator extends Shield\Databases\Utility\QueueDbRecordsMigrator {

	public function __construct() {
		parent::__construct( 'db_upgrader_iplists' );
	}

	protected function getDbSelector() {
		return null;
	}

	protected function processRecord( $record ) {
		return $record;
	}
}
