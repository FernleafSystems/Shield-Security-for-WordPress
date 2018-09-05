<?php

if ( class_exists( 'ICWP_WPSF_Processor_Plugin_Notes' ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/basedb.php' );

class ICWP_WPSF_Processor_Plugin_Notes extends ICWP_WPSF_BaseDbProcessor {

	/**
	 * @param ICWP_WPSF_FeatureHandler_Plugin $oModCon
	 */
	public function __construct( ICWP_WPSF_FeatureHandler_Plugin $oModCon ) {
		parent::__construct( $oModCon, $oModCon->getDbNameNotes() );
	}

	public function run() {
	}

	/**
	 * @return string
	 */
	public function getCreateTableSql() {
		$sSqlTables = "CREATE TABLE %s (
			id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			wp_username varchar(255) NOT NULL DEFAULT 'unknown',
			note TEXT,
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
		$aDef = $this->getMod()->getDef( 'db_notes_table_columns' );
		return ( is_array( $aDef ) ? $aDef : array() );
	}

	/**
	 * @return ICWP_WPSF_Query_PluginNotes_Insert
	 */
	public function getQueryInserter() {
		$this->queryRequireLib( 'insert.php' );
		$oQ = new ICWP_WPSF_Query_PluginNotes_Insert();
		return $oQ->setTable( $this->getTableName() );
	}

	/**
	 * @return ICWP_WPSF_Query_PluginNotes_Delete
	 */
	public function getQueryDeleter() {
		$this->queryRequireLib( 'delete.php' );
		$oQ = new ICWP_WPSF_Query_PluginNotes_Delete();
		return $oQ->setTable( $this->getTableName() );
	}

	/**
	 * @return ICWP_WPSF_Query_PluginNotes_Select
	 */
	public function getQuerySelector() {
		$this->queryRequireLib( 'select.php' );
		$oQ = new ICWP_WPSF_Query_PluginNotes_Select();
		return $oQ->setTable( $this->getTableName() );
	}

	/**
	 * @return string
	 */
	protected function queryGetDir() {
		return parent::queryGetDir().'notes/';
	}
}