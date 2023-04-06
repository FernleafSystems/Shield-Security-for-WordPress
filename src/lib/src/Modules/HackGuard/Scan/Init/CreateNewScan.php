<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\Scans;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Exceptions\ScanExistsException;
use FernleafSystems\Wordpress\Services\Services;

class CreateNewScan {

	use ModConsumer;

	/**
	 * @throws ScanExistsException|\Exception
	 */
	public function run( string $slug ) :Scans\Ops\Record {
		if ( $this->scanExists( $slug ) ) {
			throw new ScanExistsException( $slug );
		}

		$dbh = $this->mod()->getDbH_Scans();
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
		/** @var Scans\Ops\Select $selector */
		$selector = $this->mod()->getDbH_Scans()->getQuerySelector();
		return $selector->filterByScan( $slug )
						->filterByNotFinished()
						->count() > 0;
	}
}