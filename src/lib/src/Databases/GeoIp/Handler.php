<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\GeoIp;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Options;

class Handler extends Base\Handler {

	public function autoCleanDb() {
		$this->tableCleanExpired( $this->getOptions()->getDef( 'db_autoexpire_geoip' ) );
	}

	/**
	 * @return string[]
	 */
	public function getColumns() {
		return $this->getOptions()->getDef( 'geoip_table_columns' );
	}

	/**
	 * @return string
	 */
	protected function getDefaultTableName() {
		/** @var Options $opts */
		$opts = $this->getOptions();
		return $opts->getDbTable_GeoIp();
	}

	/**
	 * @return string
	 */
	protected function getDefaultCreateTableSql() {
		return "CREATE TABLE %s (
			id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			ip varbinary(16) DEFAULT NULL COMMENT 'IP Address',
			meta TEXT,
			created_at int(15) UNSIGNED NOT NULL DEFAULT 0,
			deleted_at int(15) UNSIGNED NOT NULL DEFAULT 0,
 			PRIMARY KEY  (id)
		) %s;";
	}
}