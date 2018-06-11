<?php

if ( class_exists( 'ICWP_WPSF_Processor_Plugin_Notes' ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/basedb.php' );

class ICWP_WPSF_Processor_Plugin_Notes extends ICWP_WPSF_BaseDbProcessor {

	/**
	 * @param ICWP_WPSF_FeatureHandler_Plugin $oModCon
	 * @throws Exception
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
		$aDef = $this->getFeature()->getDef( 'db_notes_table_columns' );
		return ( is_array( $aDef ) ? $aDef : array() );
	}

	/**
	 * @param string $nId
	 * @return bool|int
	 */
	public function deleteNote( $nId ) {
		return $this->getQueryDeleter()->delete( $nId );
	}

	/**
	 * @param string $sNote
	 * @return bool|int
	 */
	public function insertNote( $sNote ) {
		return $this->getQueryCreator()->create( $sNote );
	}

	/**
	 * @return ICWP_WPSF_Query_PluginNotes_Create
	 */
	public function getQueryCreator() {
		require_once( $this->getQueryDir().'plugin_notes_create.php' );
		$oQ = new ICWP_WPSF_Query_PluginNotes_Create();
		return $oQ->setTable( $this->getTableName() );
	}

	/**
	 * @return ICWP_WPSF_Query_PluginNotes_Delete
	 */
	public function getQueryDeleter() {
		require_once( $this->getQueryDir().'plugin_notes_delete.php' );
		$oQ = new ICWP_WPSF_Query_PluginNotes_Delete();
		return $oQ->setTable( $this->getTableName() );
	}

	/**
	 * @return ICWP_WPSF_Query_PluginNotes_Retrieve
	 */
	public function getQueryRetriever() {
		require_once( $this->getQueryDir().'plugin_notes_retrieve.php' );
		$oQ = new ICWP_WPSF_Query_PluginNotes_Retrieve();
		return $oQ->setTable( $this->getTableName() );
	}
}