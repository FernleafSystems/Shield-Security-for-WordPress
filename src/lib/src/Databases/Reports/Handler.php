<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Reports;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Options;

class Handler extends Base\EnumeratedColumnsHandler {

	const TYPE_ALERT = 'alt';
	const TYPE_INFO = 'nfo';

	public function autoCleanDb() {
		$this->tableCleanExpired( 30 );
	}

	protected function getColumnsAsArray() :array {
		return $this->getOptions()->getDef( 'reports_table_columns' );
	}

	protected function getDefaultTableName() :string {
		/** @var Options $opts */
		$opts = $this->getOptions();
		return $opts->getDbTable_Reports();
	}

	protected function getTimestampColumnNames() :array {
		return $this->getOptions()->getDef( 'reports_table_timestamp_columns' );
	}
}