<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\BotSignals;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\IPs\IPRecords;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\BotSignal;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class ConvertLegacy {

	use ModConsumer;

	public function run() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$opts = $this->getOptions();

		if ( empty( $opts->getOpt( 'legacy_db_deleted_at' ) ) ) {
			$this->convert();
			$dbh = $mod->getDbHandler_BotSignals();
			if ( $dbh->getQuerySelector()->count() === 0 ) {
				$opts->setOpt( 'legacy_db_deleted_at', Services::Request()->ts() );
				$dbh->tableDelete();
			}
		}
	}

	private function convert() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$dbh = $mod->getDbHandler_BotSignals();

		$toDelete = [];

		/** @var BotSignals\EntryVO $entry */
		foreach ( $dbh->getIterator() as $entry ) {

			try {
				$this->createPrimaryLogRecord( $entry );
			}
			catch ( \Exception $e ) {
			}
			finally {
				$toDelete[] = $entry->id;
			}
		}

		if ( !empty( $toDelete ) ) {
			$dbh->getQueryDeleter()
				->addWhereIn( 'in', $toDelete )
				->query();
		}
	}

	/**
	 * @param BotSignals\EntryVO $entry
	 * @return bool
	 * @throws \Exception
	 */
	protected function createPrimaryLogRecord( BotSignals\EntryVO $entry ) :bool {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$dbh = $mod->getDbH_BotSignal();

		if ( empty( $entry->ip ) ) {
			throw new \Exception( 'No IP' );
		}

		$ipRecord = ( new IPRecords() )
			->setMod( $this->getCon()->getModule_Data() )
			->loadIP( $entry->ip );
		unset( $entry->ip );

		/** @var BotSignal\Ops\Select $selector */
		$selector = $dbh->getQuerySelector();
		if ( $selector->filterByIP( $ipRecord->id )->count() > 0 ) {
			throw new \Exception( 'Record already exists' );
		}

		/** @var BotSignal\Ops\Record $record */
		$record = $dbh->getRecord()->applyFromArray( $entry->getRawData() );
		unset( $record->id );
		$record->ip_ref = $ipRecord->id;

		$success = $mod->getDbH_BotSignal()
					   ->getQueryInserter()
					   ->insert( $record );
		if ( !$success ) {
			throw new \Exception( 'Failed to insert' );
		}

		return true;
	}
}
