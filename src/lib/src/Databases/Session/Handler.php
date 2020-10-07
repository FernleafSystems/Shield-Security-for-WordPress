<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Session;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Sessions\Options;

class Handler extends Base\EnumeratedColumnsHandler {

	public function autoCleanDb() {
		$this->tableCleanExpired( 30 );
	}

	/**
	 * @return string[]
	 */
	protected function getColumnsAsArray() :array {
		return $this->getOptions()->getDef( 'sessions_table_columns' );
	}

	/**
	 * @return string
	 */
	protected function getDefaultTableName() :string {
		/** @var Options $opts */
		$opts = $this->getOptions();
		return $opts->getDbTable_Sessions();
	}
}