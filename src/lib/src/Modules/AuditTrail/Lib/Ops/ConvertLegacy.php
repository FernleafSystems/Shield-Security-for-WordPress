<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\AuditTrail;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\ModCon;
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

		$metaInserter = $mod->getDbH_Meta()->getQueryInserter();

		$toDelete = [];

		/** @var AuditTrail\EntryVO $entry */
		foreach ( $mod->getDbHandler_AuditTrail()->getIterator() as $entry ) {

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

		$mod->getDbHandler_AuditTrail()
			->getQueryDeleter()
			->addWhereIn( 'in', $toDelete )
			->query();
	}

	/**
	 * @param AuditTrail\EntryVO $entry
	 * @return Logs\Ops\Record
	 * @throws \Exception
	 */
	protected function createPrimaryLogRecord( AuditTrail\EntryVO $entry ) :Logs\Ops\Record {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$record = new Logs\Ops\Record();
		$record->event_slug = $entry->event;
		$record->site_id = 1;
		$record->ip = $entry->ip;
		$record->rid = $entry->rid;
		$record->created_at = $entry->created_at;

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
