<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\ScanQueue;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Options;

class Handler extends Base\Handler {

	/**
	 * @return string[]
	 */
	public function getColumns() {
		/** @var Options $oOpts */
		$oOpts = $this->getOptions();
		return $oOpts->getDbColumns_ScanQueue();
	}

	/**
	 * @return string
	 */
	protected function getDefaultTableName() {
		/** @var Options $oOpts */
		$oOpts = $this->getOptions();
		return $oOpts->getDbTable_ScanQueue();
	}

	/**
	 * @return string
	 */
	protected function getDefaultCreateTableSql() {
		return "CREATE TABLE %s (
			id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			scan varchar(3) NOT NULL DEFAULT 0 COMMENT 'Scan Slug',
			items text COMMENT 'Array of scan items',
			results text COMMENT 'Array of results',
			meta text COMMENT 'Meta Data',
			started_at int(15) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'TS Started',
			finished_at int(15) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'TS Finished',
			created_at int(15) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'TS Created',
			deleted_at int(15) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'TS Soft Deleted',
			PRIMARY KEY  (id)
		) %s;";
	}
}