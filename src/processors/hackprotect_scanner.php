<?php

if ( class_exists( 'ICWP_WPSF_Processor_HackProtect_Scanner', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/basedb.php' );

class ICWP_WPSF_Processor_HackProtect_Scanner extends ICWP_WPSF_BaseDbProcessor {

	/**
	 */
	public function run() {
	}

	/**
	 * @return ICWP_WPSF_AuditTrailEntryVO
	 */
	protected function getEntryVo() {
		/** @var ICWP_WPSF_AuditTrailEntryVO $oVo */
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
			rid varchar(10) NOT NULL DEFAULT '',
			ip varchar(40) NOT NULL DEFAULT 0,
			wp_username varchar(255) NOT NULL DEFAULT 'none',
			context varchar(32) NOT NULL DEFAULT 'none',
			event varchar(50) NOT NULL DEFAULT 'none',
			category int(3) UNSIGNED NOT NULL DEFAULT 0,
			message text,
			immutable tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
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
		$aDef = $this->getMod()->getDef( 'table_columns_scanner' );
		return ( is_array( $aDef ) ? $aDef : array() );
	}
}