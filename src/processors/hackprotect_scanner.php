<?php

if ( class_exists( 'ICWP_WPSF_Processor_HackProtect_Scanner', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/basedb.php' );

class ICWP_WPSF_Processor_HackProtect_Scanner extends ICWP_WPSF_BaseDbProcessor {

	/**
	 * ICWP_WPSF_Processor_HackProtect_Scanner constructor.
	 * @param $oModCon
	 */
	public function __construct( $oModCon ) {
		parent::__construct( $oModCon, $oModCon->getDef( 'table_name_scanner' ) );
	}

	/**
	 */
	public function run() {
	}

	/**
	 * @return ICWP_WPSF_ScannerEntryVO
	 */
	protected function getEntryVo() {
		/** @var ICWP_WPSF_ScannerEntryVO $oVo */
		$oVo = $this->getQuerySelector()
					->getVo();
		return $oVo;
	}

	/**
	 * @return ICWP_WPSF_Query_Scanner_Delete
	 */
	public function getQueryDeleter() {
		$this->queryRequireLib( 'delete.php' );
		$oQ = new ICWP_WPSF_Query_Scanner_Delete();
		return $oQ->setTable( $this->getTableName() );
	}

	/**
	 * @return ICWP_WPSF_Query_Scanner_Insert
	 */
	public function getQueryInserter() {
		$this->queryRequireLib( 'insert.php' );
		$oQ = new ICWP_WPSF_Query_Scanner_Insert();
		return $oQ->setTable( $this->getTableName() );
	}

	/**
	 * @return ICWP_WPSF_Query_Scanner_Select
	 */
	public function getQuerySelector() {
		$this->queryRequireLib( 'select.php' );
		$oQ = new ICWP_WPSF_Query_Scanner_Select();
		return $oQ->setTable( $this->getTableName() );
	}

	/**
	 * @return string
	 */
	protected function queryGetDir() {
		return path_join( parent::queryGetDir(), 'scanner/' );
	}

	/**
	 * @return string
	 */
	protected function getCreateTableSql() {
		$sSqlTables = "CREATE TABLE %s (
			id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			hash varchar(32) NOT NULL DEFAULT '' COMMENT 'Unique Item Hash',
			data text COMMENT 'Relevant Item Data',
			description text COMMENT 'Human Description',
			scan varchar(10) NOT NULL DEFAULT 0 COMMENT 'Scan Type',
			severity int(3) NOT NULL DEFAULT 1 COMMENT 'Severity',
			repaired_at int(15) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'TS Repaired',
			created_at int(15) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'TS Discovered',
			deleted_at int(15) UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY  (id)
		) %s;";
		return sprintf( $sSqlTables, $this->getTableName(), $this->loadDbProcessor()->getCharCollate() );
	}

	/**
	 * @return array
	 */
	protected function getTableColumnsByDefinition() {
		$aDef = $this->getMod()->getDef( 'table_columns_scanner' );
		return ( is_array( $aDef ) ? $aDef : array() );
	}
}