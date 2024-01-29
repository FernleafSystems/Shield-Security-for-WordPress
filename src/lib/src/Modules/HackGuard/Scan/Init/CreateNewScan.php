<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\Scans\Ops as ScansDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Exceptions\ScanExistsException;
use FernleafSystems\Wordpress\Services\Services;

class CreateNewScan {

	use ModConsumer;

	/**
	 * @throws ScanExistsException|\Exception
	 */
	public function run( string $slug ) :ScansDB\Record {
		if ( $this->scanExists( $slug ) ) {
			throw new ScanExistsException( $slug );
		}

		$dbh = self::con()->db_con->dbhScans();
		/** @var ScansDB\Record $record */
		$record = $dbh->getRecord();
		$record->scan = $slug;
		$success = $dbh->getQueryInserter()->insert( $record );
		if ( !$success ) {
			throw new \Exception( sprintf( 'Failed to create/insert a new scan "%s".', $slug ) );
		}

		return $dbh->getQuerySelector()->byId( Services::WpDb()->getVar( 'SELECT LAST_INSERT_ID()' ) );
	}

	private function scanExists( string $slug ) :bool {
		/** @var ScansDB\Select $selector */
		$selector = self::con()->db_con->dbhScans()->getQuerySelector();
		return $selector->filterByScan( $slug )
						->filterByNotFinished()
						->count() > 0;
	}
}