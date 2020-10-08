<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Reports;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Options;

class Handler extends Base\Handler {

	const TYPE_ALERT = 'alt';
	const TYPE_INFO = 'nfo';

	public function autoCleanDb() {
		$this->tableCleanExpired( 30 );
	}

	protected function getCustomColumns() :array {
		return $this->getOptions()->getDef( 'reports_table_columns' );
	}

	protected function getDefaultTableName() :string {
		return $this->getOptions()->getDef( 'reports_table_name' );
	}

	protected function getTimestampColumns() :array {
		return $this->getOptions()->getDef( 'reports_table_timestamp_columns' );
	}
}