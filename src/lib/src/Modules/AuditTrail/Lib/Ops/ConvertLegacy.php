<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\AuditTrail;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\IPs\IPRecords;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\DB\ReqLogs\RequestRecords;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\DB\{
	Logs,
	Meta
};
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
			$dbh = $mod->getDbHandler_AuditTrail();
			if ( $dbh->getQuerySelector()->count() === 0 ) {
				$opts->setOpt( 'legacy_db_deleted_at', Services::Request()->ts() );
			}
			$dbh->tableDelete();
		}
	}

	private function convert() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$dbh = $mod->getDbHandler_AuditTrail();

		$metaInserter = $mod->getDbH_Meta()->getQueryInserter();

		$toDelete = [];

		/** @var AuditTrail\EntryVO $entry */
		foreach ( $dbh->getIterator() as $entry ) {

			try {
				$log = $this->createPrimaryLogRecord( $entry );

				$metaRecord = new Meta\Ops\Record();
				$metaRecord->log_ref = $log->id;

				$uid = '';
				if ( $entry->wp_username === 'WP Cron' ) {
					$uid = 'cron';
				}
				elseif ( $entry->wp_username === 'WP CLI' ) {
					$uid = 'cli';
				}
				elseif ( $entry->wp_username !== '-' ) {
					$user = Services::WpUsers()->getUserByUsername( $entry->wp_username );
					if ( $user instanceof \WP_User ) {
						$uid = $user->ID;
					}
				}

				if ( !empty( $uid ) ) {
					$metaRecord->meta_key = 'uid';
					$metaRecord->meta_value = $uid;
					$metaInserter->insert( $metaRecord );
				}

				foreach ( $entry->meta as $metaKey => $metaValue ) {
					$metaRecord->meta_key = $metaKey;
					$metaRecord->meta_value = $metaValue;
					$mod->getDbH_Meta()
						->getQueryInserter()
						->insert( $metaRecord );
				}
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
		// TODO: set hidden marker to say completed and delete table
	}

	/**
	 * @param AuditTrail\EntryVO $entry
	 * @return Logs\Ops\Record
	 * @throws \Exception
	 */
	protected function createPrimaryLogRecord( AuditTrail\EntryVO $entry ) :Logs\Ops\Record {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		if ( empty( $entry->rid ) || empty( $entry->ip ) ) {
			throw new \Exception( 'No RID or IP' );
		}

		if ( !$this->getCon()->loadEventsService()->eventExists( (string)$entry->event ) ) {
			throw new \Exception( 'Not a supported event' );
		}

		$record = new Logs\Ops\Record();
		$record->event_slug = $entry->event;
		$record->site_id = 1;
		$record->created_at = $entry->created_at;

		$ipID = ( new IPRecords() )
			->setMod( $this->getCon()->getModule_Data() )
			->loadIP( $entry->ip )
			->id;
		$record->req_ref = ( new RequestRecords() )
			->setMod( $this->getCon()->getModule_Traffic() )
			->loadReq( $entry->rid, $ipID )
			->id;

		$success = $mod->getDbH_Logs()
					   ->getQueryInserter()
					   ->insert( $record );
		if ( !$success ) {
			throw new \Exception( 'Failed to insert' );
		}

		/** @var Logs\Ops\Record $log */
		$log = $mod->getDbH_Logs()
				   ->getQuerySelector()
				   ->byId( Services::WpDb()->getVar( 'SELECT LAST_INSERT_ID()' ) );
		if ( empty( $log ) ) {
			throw new \Exception( 'Could not load log record' );
		}
		return $log;
	}
}
