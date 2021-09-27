<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\Scans;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Exceptions\ScanExistsException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class CreateNewScan {

	use ModConsumer;

	/**
	 * @param string $slug
	 * @return Scans\Ops\Record
	 * @throws ScanExistsException|\Exception
	 */
	public function run( string $slug ) :Scans\Ops\Record {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		// Delete any stale scans
		$this->deleteStaleScan( $slug );

		if ( $this->scanExists( $slug ) ) {
			throw new ScanExistsException( $slug );
		}

		$dbh = $mod->getDbH_Scans();
		/** @var Scans\Ops\Record $record */
		$record = $dbh->getRecord();
		$record->scan = $slug;
		$success = $dbh->getQueryInserter()->insert( $record );
		if ( !$success ) {
			throw new \Exception( sprintf( 'Failed to create/insert a new scan "%s".', $slug ) );
		}

		return $dbh->getQuerySelector()->byId( Services::WpDb()->getVar( 'SELECT LAST_INSERT_ID()' ) );
	}

	private function scanExists( string $slug ) :bool {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var Scans\Ops\Select $selector */
		$selector = $mod->getDbH_Scans()->getQuerySelector();
		return $selector->filterByScan( $slug )
						->filterByNotFinished()
						->count() > 0;
	}

	private function deleteStaleScan( string $slug ) :bool {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var Scans\Ops\Delete $deleter */
		$deleter = $mod->getDbH_Scans()->getQueryDeleter();
		return $deleter->filterByScan( $slug )
					   ->filterByStale()
					   ->query();
	}
}