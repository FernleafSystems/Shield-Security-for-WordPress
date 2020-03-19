<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\FileLocker;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Options;

class Handler extends Base\Handler {

	/**
	 * @return string[]
	 */
	protected function getDefaultColumnsDefinition() {
		/** @var Options $oOpts */
		$oOpts = $this->getOptions();
		return $oOpts->getDbColumns_FileLocker();
	}

	/**
	 * @return string
	 */
	protected function getDefaultTableName() {
		/** @var Options $oOpts */
		$oOpts = $this->getOptions();
		return $oOpts->getDbTable_FileLocker();
	}

	/**
	 * @return string
	 */
	protected function getDefaultCreateTableSql() {
		return "CREATE TABLE %s (
			id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			file varchar(256) NOT NULL COMMENT 'File Path relative to ABSPATH',
			hash varchar(40) NOT NULL COMMENT 'SHA1 File Hash',
			content blob COMMENT 'Content',
			meta text COMMENT 'Meta Data',
			encrypted TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Is Content Encrypted',
			updated_at int(15) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'TS Updated',
			created_at int(15) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'TS Created',
			deleted_at int(15) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'TS Soft Deleted',
			PRIMARY KEY  (id)
		) %s;";
	}
}