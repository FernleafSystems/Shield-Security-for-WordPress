<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\Scans;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class CreateNewScan {

	use ModConsumer;

	/**
	 * @throws \Exception
	 */
	public function run( string $slug ) :Scans\Ops\Record {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		if ( $this->scanExists( $slug ) ) {
			throw new \Exception( sprintf( 'Scan "%s" is already created and unfinished.', $slug ) );
		}

		$dbh = $mod->getDbH_Scans();
		/** @var Scans\Ops\Record $record */
		$record = $dbh->getRecord();
		$record->scan = $slug;
		$success = $dbh->getQueryInserter()->insert( $record );
		if ( !$success ) {
			throw new \Exception( sprintf( 'Failed to create/insert a new scan "%s".', $slug ) );
		}
		/** @var Scans\Ops\Select $select */
		$select = $dbh->getQuerySelector();
		return $select->filterByScan( $slug )
					  ->filterByNotStarted()
					  ->first();
	}

	private function scanExists( string $slug ) :bool {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var Scans\Ops\Select $dbh */
		$dbh = $mod->getDbH_Scans()->getQuerySelector();
		return $dbh->filterByScan( $slug )
				   ->filterByNotFinished()
				   ->count() > 0;
	}
}
