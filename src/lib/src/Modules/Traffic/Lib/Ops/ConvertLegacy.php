<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Traffic;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\DB\IPs\IPRecords;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\DB\ReqLogs\RequestRecords;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\DB\{
	ReqLogs,
	ReqMeta
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class ConvertLegacy {

	use ModConsumer;

	public function run() {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$metaInserter = $mod->getDbH_ReqMeta()->getQueryInserter();

		$toDelete = [];

		/** @var Traffic\EntryVO $entry */
		foreach ( $mod->getDbHandler_Traffic()->getIterator() as $entry ) {

			try {
				$log = $this->createPrimaryLogRecord( $entry );

				$metaRecord = new ReqMeta\Ops\Record();
				$metaRecord->log_ref = $log->id;

				$metaKeys = [
					'uid',
					'path',
					'code',
					'verb',
					'ua',
					'trans',
				];

				foreach ( $metaKeys as $metaKey ) {
					if ( !empty( $entry->{$metaKey} ) ) {
						$metaRecord->meta_key = ( $metaKey === 'trans' ) ? 'is_offense' : $metaKey;
						$metaRecord->meta_value = $entry->{$metaKey};
						$metaInserter->insert( $metaRecord );
					}
				}
			}
			catch ( \Exception $e ) {
			}
			finally {
				$toDelete[] = $entry->id;
			}
		}

		if ( !empty( $toDelete ) ) {
			$mod->getDbHandler_Traffic()
				->getQueryDeleter()
				->addWhereIn( 'in', $toDelete )
				->query();
		}
		// TODO: set hidden marker to say completed and delete table
	}

	/**
	 * @param Traffic\EntryVO $entry
	 * @return ReqLogs\Ops\Record
	 * @throws \Exception
	 */
	protected function createPrimaryLogRecord( Traffic\EntryVO $entry ) :ReqLogs\Ops\Record {
		if ( empty( $entry->rid ) || empty( $entry->ip ) ) {
			throw new \Exception( 'No RID or IP' );
		}

		$record = new ReqLogs\Ops\Record();
		$record->created_at = $entry->created_at;

		$ipID = ( new IPRecords() )
			->setMod( $this->getCon()->getModule_Plugin() )
			->loadIP( $entry->ip )
			->id;
		$record = ( new RequestRecords() )
			->setMod( $this->getCon()->getModule_Traffic() )
			->loadReq( $entry->rid, $ipID );
		if ( empty( $record ) ) {
			throw new \Exception( 'Failed to insert' );
		}
		return $record;
	}
}
