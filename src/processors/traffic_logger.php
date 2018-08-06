<?php

if ( class_exists( 'ICWP_WPSF_Processor_Traffic', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/basedb.php' );

class ICWP_WPSF_Processor_TrafficLogger extends ICWP_WPSF_BaseDbProcessor {

	/**
	 * @param ICWP_WPSF_Processor_Traffic $oModCon
	 */
	public function __construct( ICWP_WPSF_FeatureHandler_Traffic $oModCon ) {
		parent::__construct( $oModCon, $oModCon->getTrafficTableName() );
	}

	/**
	 * Override to set what this processor does when it's "run"
	 */
	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_Traffic $oFO */
		$oFO = $this->getFeature();
	}

	/**
	 * @return string
	 */
	protected function getCreateTableSql() {
		$sSqlTables = "CREATE TABLE %s (
			id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			uid int(11) UNSIGNED NOT NULL DEFAULT 0,
			ip binary(16) DEFAULT NULL,
			path text NOT NULL DEFAULT '',
			code int(5) NOT NULL DEFAULT '200',
			ref text NOT NULL DEFAULT '',
			ua text NOT NULL DEFAULT '',
			verb varchar(10) NOT NULL DEFAULT 'GET',
			payload text NOT NULL DEFAULT '',
			created_at int(15) UNSIGNED NOT NULL DEFAULT 0,
			deleted_at int(15) UNSIGNED NOT NULL DEFAULT 0,
 			PRIMARY KEY  (id)
		) %s;";
		return sprintf( $sSqlTables, $this->getTableName(), $this->loadDbProcessor()->getCharCollate() );
	}

	/**
	 * @return array
	 */
	protected function getTableColumnsByDefinition() {
		$aDef = $this->getFeature()->getDef( 'traffic_table_columns' );
		return is_array( $aDef ) ? $aDef : array();
	}
}