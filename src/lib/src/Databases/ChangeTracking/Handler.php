<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\ChangeTracking;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Options;

class Handler extends Base\Handler {

	/**
	 * @return string[]
	 */
	public function getColumns() {
		return $this->getOptions()->getDef( 'table_columns_changetracking' );
	}

	/**
	 * @return string
	 */
	protected function getDefaultTableName() :string {
		/** @var Options $opts */
		$opts = $this->getOptions();
		return $opts->getDbTable_ChangeTracking();
	}

	/**
	 * @return string
	 */
	protected function getDefaultCreateTableSql() {
		return "CREATE TABLE %s (
			id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			data BLOB NOT NULL DEFAULT '' COMMENT 'Snapshot Data',
			meta TEXT NOT NULL DEFAULT '' COMMENT 'Snapshot Meta',
			created_at int(15) UNSIGNED NOT NULL DEFAULT 0,
			deleted_at int(15) UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY  (id)
		) %s;";
	}
}