<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Scans\Ops as ScansDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Exceptions\{
	ScanCreateException,
	ScanExistsException
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\ScanStatus;
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
		$existingScanID = $this->existingActiveScanID( $slug, $scopeType, $scopeKey );
		if ( $existingScanID > 0 ) {
			throw new ScanExistsException( $slug, $existingScanID );
		}

		$dbh = self::con()->db_con->scans;
		$now = Services::Request()->ts();
		/** @var ScansDB\Record $record */
		$record = $dbh->getRecord();
		$record->scan = $slug;
		$record->status = ScanStatus::QUEUED;
		$record->scope_type = $scopeType;
		$record->scope_key = $scopeKey;
		$record->run_trigger = $runTrigger;
		$record->created_at = $now;
		$record->started_at = 0;
		$record->last_process_at = 0;
		$record->ready_at = 0;
		$record->finished_at = 0;
		$record->meta = [];
		$success = $dbh->getQueryInserter()->insert( $record );
		if ( !$success ) {
			throw new ScanCreateException( $slug );
		}

		$record->id = $this->lastInsertID();
		return $record;
	}

	private function existingActiveScanID( string $slug, string $scopeType, string $scopeKey ) :int {
		/** @var ScansDB\Select $selector */
		$selector = self::con()->db_con->scans->getQuerySelector();
		$record = $selector->setColumnsToSelect( [ 'id' ] )
						   ->filterByScan( $slug )
						   ->filterByScope( $scopeType, $scopeKey )
						   ->filterByNotFinished()
						   ->addWhereIn( 'status', ScanStatus::ACTIVE )
						   ->first();

		return (int)( $record->id ?? 0 );
	}

	private function lastInsertID() :int {
		global $wpdb;
		return (int)( \is_object( $wpdb ) && isset( $wpdb->insert_id ) ?
			$wpdb->insert_id
			: Services::WpDb()->getVar( 'SELECT LAST_INSERT_ID()' ) );
	}
}
