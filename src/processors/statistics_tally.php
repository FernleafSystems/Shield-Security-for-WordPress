<?php

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Tally;

class ICWP_WPSF_Processor_Statistics_Tally extends ICWP_WPSF_BaseDbProcessor {

	/**
	 * @param ICWP_WPSF_FeatureHandler_Statistics $oModCon
	 */
	public function __construct( ICWP_WPSF_FeatureHandler_Statistics $oModCon ) {
		parent::__construct( $oModCon, $oModCon->getDef( 'statistics_table_name' ) );
	}

	public function run() {
	}

	/**
	 * @return Tally\Handler
	 */
	protected function createDbHandler() {
		return new Tally\Handler();
	}

	/**
	 * @return array
	 */
	protected function getTableColumnsByDefinition() {
		$aDef = $this->getMod()->getDef( 'statistics_table_columns' );
		return ( is_array( $aDef ) ? $aDef : [] );
	}

	/**
	 */
	protected function commit() {
	}

	/**
	 * @return string
	 */
	protected function getCreateTableSql() {
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
	 */
	public function cleanupDatabase() {
		$this->consolidateDuplicateKeys();
	}

	/**
	 * Will consolidate multiple rows with the same stat_key into 1 row
	 */
	protected function consolidateDuplicateKeys() {
	}
}