<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Reports;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Options;

class Handler extends Base\Handler {

	public function autoCleanDb() {
		$this->cleanDb( 30 );
	}

	/**
	 * @return string[]
	 */
	protected function getDefaultColumnsDefinition() {
		/** @var Options $oOpts */
		$oOpts = $this->getOptions();
		return $oOpts->getDbColumns_Reports();
	}

	/**
	 * @return string
	 */
	protected function getDefaultTableName() {
		/** @var Options $oOpts */
		$oOpts = $this->getOptions();
		return $oOpts->getDbTable_Reports();
	}

	/**
	 * @return string
	 */
	protected function getDefaultCreateTableSql() {
		return "CREATE TABLE %s (
			id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			rid int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Report ID',
			type varchar(3) NOT NULL DEFAULT '' COMMENT 'Report Type',
			from_ts int(15) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'TS Report Gathered From',
			until_ts int(15) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'TS Report Gathered Until',
			updated_at int(15) UNSIGNED NOT NULL DEFAULT 0,
			created_at int(15) UNSIGNED NOT NULL DEFAULT 0,
			deleted_at int(15) UNSIGNED NOT NULL DEFAULT 0,
 			PRIMARY KEY  (id)
		) %s;";
	}
}