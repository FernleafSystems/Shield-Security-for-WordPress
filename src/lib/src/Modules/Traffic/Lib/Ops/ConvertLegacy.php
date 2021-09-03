<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Traffic;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\IPs\IPRecords;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\ReqLogs;
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
			$dbh = $mod->getDbHandler_Traffic();
			if ( $dbh->getQuerySelector()->count() === 0 ) {
				$opts->setOpt( 'legacy_db_deleted_at', Services::Request()->ts() );
			}
			$dbh->tableDelete();
		}
	}

	private function convert() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$dbh = $mod->getDbHandler_Traffic();

		$toDelete = [];

		/** @var Traffic\EntryVO $entry */
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

	protected function createPrimaryLogRecord( Traffic\EntryVO $entry ) :bool {
		$modData = $this->getCon()->getModule_Data();

		if ( empty( $entry->rid ) || empty( $entry->ip ) ) {
			throw new \Exception( 'No RID or IP' );
		}

		$meta = [];
		foreach ( [ 'uid', 'path', 'code', 'verb', 'ua', 'trans', ] as $metaKey ) {
			if ( !empty( $entry->{$metaKey} ) ) {
				$meta[ $metaKey ] = ( $metaKey === 'trans' ) ? 'offense' : $metaKey;
			}
		}

		/** @var ReqLogs\Ops\Record $record */
		$record = $modData->getDbH_ReqLogs()->getRecord();
		$record->req_id = $entry->rid;
		$record->ip_ref = ( new IPRecords() )
			->setMod( $modData )
			->loadIP( $entry->ip )
			->id;
		$record->meta = $meta;
		$record->created_at = $entry->created_at;

		return $modData->getDbH_ReqLogs()
					   ->getQueryInserter()
					   ->insert( $record );
	}
}
