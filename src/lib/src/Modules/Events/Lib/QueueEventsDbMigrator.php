<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Events\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Utility\QueueDbRecordsMigrator;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Events\ModConsumer;

class QueueEventsDbMigrator extends QueueDbRecordsMigrator {

	use ModConsumer;

	public function __construct() {
		parent::__construct( 'db_migrator_events' );
	}

	protected function getDbSelector() {
		return null;
	}

	protected function processRecord( $entry ) {
	}
}