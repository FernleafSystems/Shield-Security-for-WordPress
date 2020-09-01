<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Traffic;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Options;

class Handler extends Base\Handler {

	public function autoCleanDb() {
		/** @var Options $opts */
		$opts = $this->getOptions();
		$this->tableCleanExpired( $opts->getAutoCleanDays() );
		$this->tableTrimExcess( $opts->getMaxEntries() );
	}

	/**
	 * @return string[]
	 */
	public function getColumns() {
		return $this->getOptions()->getDef( 'traffic_table_columns' );
	}

	/**
	 * @return string
	 */
	protected function getDefaultTableName() {
		/** @var Options $opts */
		$opts = $this->getOptions();
		return $opts->getDbTable_TrafficLog();
	}

	/**
	 * @return string
	 */
	protected function getDefaultCreateTableSql() {
		return "CREATE TABLE %s (
			id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			rid varchar(10) NOT NULL DEFAULT '' COMMENT 'Request ID',
			uid int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'User ID',
			ip varbinary(16) DEFAULT NULL COMMENT 'Visitor IP Address',
			path text NOT NULL DEFAULT '' COMMENT 'Request Path or URI',
			code int(5) NOT NULL DEFAULT '200' COMMENT 'HTTP Response Code',
			verb varchar(10) NOT NULL DEFAULT 'get' COMMENT 'HTTP Method',
			ua text COMMENT 'Browser User Agent String',
			trans tinyint(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Trangression',
			created_at int(15) UNSIGNED NOT NULL DEFAULT 0,
			deleted_at int(15) UNSIGNED NOT NULL DEFAULT 0,
 			PRIMARY KEY  (id)
		) %s;";
	}
}