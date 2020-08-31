<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Options;

class Handler extends Base\Handler {

	/**
	 * @return string[]
	 */
	public function getColumns() {
		/** @var Options $oOpts */
		$oOpts = $this->getOptions();
		return $oOpts->getDbColumns_Scanner();
	}

	/**
	 * @return string
	 */
	protected function getDefaultTableName() {
		/** @var Options $oOpts */
		$oOpts = $this->getOptions();
		return $oOpts->getDbTable_Scanner();
	}

	/**
	 * @return string
	 */
	protected function getDefaultCreateTableSql() {
		return "CREATE TABLE %s (
			id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			hash varchar(32) NOT NULL DEFAULT '' COMMENT 'Unique Item Hash',
			meta text COMMENT 'Relevant Item Data',
			scan varchar(10) NOT NULL DEFAULT 0 COMMENT 'Scan Type',
			severity int(3) NOT NULL DEFAULT 1 COMMENT 'Severity',
			ignored_at int(15) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'TS Ignored',
			notified_at int(15) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'TS Last Notified',
			created_at int(15) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'TS Discovered',
			deleted_at int(15) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'TS Soft Deleted',
			PRIMARY KEY  (id)
		) %s;";
	}

	/**
	 * @return string[]
	 * @deprecated 9.2.0
	 */
	protected function getDefaultColumnsDefinition() {
		/** @var Options $oOpts */
		$oOpts = $this->getOptions();
		return $oOpts->getDbColumns_Scanner();
	}
}