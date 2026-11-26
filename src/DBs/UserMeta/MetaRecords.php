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

		if ( empty( $record ) && $autoCreate ) {
			// Read again whether or not the insert worked: another request may have stored the record first.
			$this->addMeta( $userID );
			$record = $this->loadMeta( $userID, false );
		}

		if ( !empty( $record ) ) {
			$record->setDbH( $dbh );
		}

		return $record;
	}

	/**
	 * The records of many users from one query. A user who has no record isn't given one.
	 * @param int[] $userIDs
	 * @return array<int,Ops\Record> the records there are, by user ID
	 */
	public function loadMetas( array $userIDs ) :array {
		$records = [];
		if ( !empty( $userIDs ) ) {
			$dbh = self::con()->db_con->user_meta;
			/** @var Ops\Select $select */
			$select = $dbh->getQuerySelector();
			$found = $select->setNoOrderBy()
							->addWhereIn( 'user_id', $userIDs )
							->queryWithResult();
			/** @var Ops\Record $record */
			foreach ( \is_array( $found ) ? $found : [] as $record ) {
				$record->setDbH( $dbh );
				$records[ $record->user_id ] = $record;
			}
		}
		return $records;
	}

	/**
	 * Stands in for the record of a user who has none, without storing anything. It's stored the first time
	 * something is written to it.
	 */
	public function unstoredFor( int $userID ) :Ops\Record {
		$dbh = self::con()->db_con->user_meta;
		/** @var Ops\Record $record */
		$record = $dbh->getRecord();
		$record->setDbH( $dbh );
		$record->user_id = $userID;
		return $record->storeOnFirstWrite();
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
