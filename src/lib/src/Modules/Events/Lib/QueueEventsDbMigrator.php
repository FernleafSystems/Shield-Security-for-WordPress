<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Events\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Events as LegacyEventsDB;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\Utility\QueueDbRecordsMigrator;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Events\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Events\DB\Event\Ops as EventsDB;

class QueueEventsDbMigrator extends QueueDbRecordsMigrator {

	use ModConsumer;

	public function __construct() {
		parent::__construct( 'db_migrator_events' );
	}

	/**
	 * @return LegacyEventsDB\Select|mixed
	 */
	protected function getDbSelector() {
		return $this->mod()
					->getDbHandler_Events()
					->getQuerySelector()
					->setOrderBy( 'id', 'ASC' );
	}

	protected function processRecord( $entry ) {
		/** @var $entry LegacyEventsDB\EntryVO */

		if ( $entry instanceof LegacyEventsDB\EntryVO ) {
			$dbh = $this->mod()->getDbH_Events();
			/** @var EventsDB\Record $record */
			$record = $dbh->getRecord();
			$record->event = $entry->event;
			$record->count = $entry->count;
			$dbh->getQueryInserter()->insert( $record );

			$this->mod()->getDbHandler_Events()->getQueryDeleter()->deleteById( $entry->id );
		}

		return $entry;
	}
}