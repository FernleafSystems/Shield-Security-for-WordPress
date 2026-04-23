<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Scans\Ops as ScansDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Exceptions\{
	ScanCreateException,
	ScanExistsException
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class CreateNewScan {

	use PluginControllerConsumer;

	/**
	 * @throws ScanCreateException|ScanExistsException
	 */
	public function run(
		string $slug,
		string $scopeType = 'full',
		string $scopeKey = '',
		string $runTrigger = 'manual'
	) :?ScansDB\Record {
		if ( $this->scanExists( $slug, $scopeType, $scopeKey ) ) {
			throw new ScanExistsException( $slug );
		}

		$dbh = self::con()->db_con->scans;
		/** @var ScansDB\Record $record */
		$record = $dbh->getRecord();
		$record->scan = $slug;
		$record->status = 'queued';
		$record->scope_type = $scopeType;
		$record->scope_key = $scopeKey;
		$record->run_trigger = $runTrigger;
		$success = $dbh->getQueryInserter()->insert( $record );
		if ( !$success ) {
			throw new ScanCreateException( $slug );
		}

		return $dbh->getQuerySelector()->byId( Services::WpDb()->getVar( 'SELECT LAST_INSERT_ID()' ) );
	}

	private function scanExists( string $slug, string $scopeType, string $scopeKey ) :bool {
		/** @var ScansDB\Select $selector */
		$selector = self::con()->db_con->scans->getQuerySelector();
		return $selector->filterByScan( $slug )
						->filterByScope( $scopeType, $scopeKey )
						->filterByNotFinished()
						->count() > 0;
	}
}
