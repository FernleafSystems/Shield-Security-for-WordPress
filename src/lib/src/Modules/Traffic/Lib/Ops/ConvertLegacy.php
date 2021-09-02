<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Traffic;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\DB\IPs\IPRecords;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\DB\ReqLogs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class ConvertLegacy {

	use ModConsumer;

	public function run() {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$toDelete = [];

		/** @var Traffic\EntryVO $entry */
		foreach ( $mod->getDbHandler_Traffic()->getIterator() as $entry ) {

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
			$mod->getDbHandler_Traffic()
				->getQueryDeleter()
				->addWhereIn( 'in', $toDelete )
				->query();
		}
		// TODO: set hidden marker to say completed and delete table
	}

	protected function createPrimaryLogRecord( Traffic\EntryVO $entry ) :bool {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		if ( empty( $entry->rid ) || empty( $entry->ip ) ) {
			throw new \Exception( 'No RID or IP' );
		}

		$meta = [];
		foreach ( [ 'uid', 'path', 'code', 'verb', 'ua', 'trans', ] as $metaKey ) {
			if ( !empty( $entry->{$metaKey} ) ) {
				$meta[ $metaKey ] = ( $metaKey === 'trans' ) ? 'offense' : $metaKey;
			}
		}

		$record = new ReqLogs\Ops\Record();
		$record->req_id = $entry->rid;
		$record->ip_ref = ( new IPRecords() )
			->setMod( $this->getCon()->getModule_Plugin() )
			->loadIP( $entry->ip )
			->id;
		$record->meta = $meta;
		$record->created_at = $entry->created_at;

		return $mod->getDbH_ReqLogs()
				   ->getQueryInserter()
				   ->insert( $record );
	}
}
