<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\UserMeta;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\IPs\IPRecords;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\ModConsumer;

class MetaRecords {

	use ModConsumer;

	public function loadMeta( int $userID, bool $autoCreate = true ) :?Ops\Record {
		$dbh = $this->mod()->getDbH_UserMeta();
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
		$dbh = $this->mod()->getDbH_UserMeta();
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