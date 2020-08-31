<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Tally;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Statistics\Options;

class Handler extends Base\Handler {

	/**
	 * @return string[]
	 */
	public function getColumns() {
		return $this->getOptions()->getDef( 'statistics_table_columns' );
	}

	/**
	 * @return string
	 */
	protected function getDefaultTableName() {
		/** @var Options $opts */
		$opts = $this->getOptions();
		return $opts->getDbTable_Tallys();
	}

	/**
	 * @return string
	 */
	protected function getDefaultCreateTableSql() {
		return "CREATE TABLE %s (
			id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			stat_key varchar(100) NOT NULL DEFAULT 0,
			parent_stat_key varchar(100) NOT NULL DEFAULT '',
			tally int(11) UNSIGNED NOT NULL DEFAULT 0,
			created_at int(15) UNSIGNED NOT NULL DEFAULT 0,
			modified_at int(15) UNSIGNED NOT NULL DEFAULT 0,
			deleted_at int(15) UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY  (id)
		) %s;";
	}

	/**
	 * @return string[]
	 * @deprecated 9.2.0
	 */
	protected function getDefaultColumnsDefinition() {
		return $this->getOptions()->getDef( 'statistics_table_columns' );
	}
}