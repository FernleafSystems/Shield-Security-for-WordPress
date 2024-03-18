<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\UserMeta;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\IPs\IPRecords;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class MetaRecords {

	use PluginControllerConsumer;

	public function loadMeta( int $userID, bool $autoCreate = true ) :?Ops\Record {
		$dbh = self::con()->db_con->user_meta;
		/** @var Ops\Select $select */
		$select = $dbh->getQuerySelector();
		$record = $select->setNoOrderBy()
						 ->filterByUser( $userID )
						 ->first();

		if ( empty( $record ) && $autoCreate && $this->addMeta( $userID ) ) {
			$record = $this->loadMeta( $userID, false );
		}

		if ( !empty( $record ) ) {
			$record->setDbH( $dbh );
		}

		return $record;
	}

	public function addMeta( int $userID ) :bool {
		$dbh = self::con()->db_con->user_meta;
		/** @var Ops\Insert $insert */
		$insert = $dbh->getQueryInserter();
		/** @var Ops\Record $record */
		$record = $dbh->getRecord();
		$record->user_id = $userID;
		$record->ip_ref = ( new IPRecords() )
			->loadIP( (string)self::con()->this_req->ip )
			->id;
		return $insert->insert( $record );
	}
}