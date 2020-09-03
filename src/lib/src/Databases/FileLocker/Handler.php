<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\FileLocker;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Options;

class Handler extends Base\Handler {

	/**
	 * @return string[]
	 */
	public function getColumns() {
		return $this->getOptions()->getDef( 'table_columns_filelocker' );
	}

	/**
	 * @return string
	 */
	protected function getDefaultTableName() {
		/** @var Options $opts */
		$opts = $this->getOptions();
		return $opts->getDbTable_FileLocker();
	}

	/**
	 * @return string
	 */
	protected function getDefaultCreateTableSql() {
		return "CREATE TABLE %s (
			id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			file varchar(256) NOT NULL COMMENT 'File Path relative to ABSPATH',
			hash_original varchar(40) NOT NULL COMMENT 'SHA1 File Hash Original',
			hash_current varchar(40) NOT NULL COMMENT 'SHA1 File Hash Current',
			content blob COMMENT 'Content',
			public_key_id TINYINT(2) UNSIGNED NOT NULL COMMENT 'Public Key ID',
			detected_at int(15) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'TS Change Last Detected',
			reverted_at int(15) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'TS Reverted To Backup',
			notified_at int(15) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'TS Notification Sent',
			updated_at int(15) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'TS Updated',
			created_at int(15) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'TS Created',
			deleted_at int(15) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'TS Soft Deleted',
			PRIMARY KEY  (id)
		) %s;";
	}

	/**
	 * @return string[]
	 * @deprecated 9.2.0
	 */
	protected function getDefaultColumnsDefinition() {
		return $this->getOptions()->getDef( 'table_columns_filelocker' );
	}
}