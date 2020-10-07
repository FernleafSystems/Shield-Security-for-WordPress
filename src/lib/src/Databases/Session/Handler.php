<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Session;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Sessions\Options;

class Handler extends Base\Handler {

	public function autoCleanDb() {
		$this->tableCleanExpired( 30 );
	}

	protected function getCustomColumns() :array {
		return $this->getOptions()->getDef( 'sessions_table_columns' );
	}

	protected function getDefaultTableName() :string {
		/** @var Options $opts */
		$opts = $this->getOptions();
		return $opts->getDbTable_Sessions();
	}

	protected function getTimestampColumns() :array {
		return $this->getOptions()->getDef( 'sessions_table_timestamp_columns' );
	}
}