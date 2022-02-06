<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\UserMeta;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class MetaRecords {

	use ModConsumer;

	/**
	 * @return Ops\Record|null
	 */
	public function loadMeta( int $userID, bool $autoCreate = true ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$dbh = $mod->getDbH_UserMeta();
		/** @var Ops\Select $select */
		$select = $dbh->getQuerySelector();
		$record = $select->filterByUser( $userID )->first();

		if ( empty( $record ) && $autoCreate && $this->addMeta( $userID ) ) {
			$record = $this->loadMeta( $userID, false );
		}

		if ( !empty( $record ) ) {
			$record->setDbHandler( $dbh );
		}

		return $record;
	}

	public function addMeta( int $userID ) :bool {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$dbh = $mod->getDbH_UserMeta();
		/** @var Ops\Insert $insert */
		$insert = $dbh->getQueryInserter();
		/** @var Ops\Record $record */
		$record = $dbh->getRecord();
		$record->user_id = $userID;
		return $insert->insert( $record );
	}
}