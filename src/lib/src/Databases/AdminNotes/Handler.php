<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\AdminNotes;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Options;

class Handler extends Base\Handler {

	/**
	 * @return string[]
	 */
	public function getColumns() {
		/** @var Options $oOpts */
		$oOpts = $this->getOptions();
		return $oOpts->getDbColumns_Notes();
	}

	/**
	 * @return string
	 */
	protected function getDefaultTableName() {
		/** @var Options $oOpts */
		$oOpts = $this->getOptions();
		return $oOpts->getDbTable_Notes();
	}

	/**
	 * @return string
	 */
	protected function getDefaultCreateTableSql() {
		return "CREATE TABLE %s (
			id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			wp_username varchar(255) NOT NULL DEFAULT 'unknown',
			note TEXT,
			created_at int(15) UNSIGNED NOT NULL DEFAULT 0,
			deleted_at int(15) UNSIGNED NOT NULL DEFAULT 0,
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
		return $oOpts->getDbColumns_Notes();
	}
}