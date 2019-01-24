<?php

class ICWP_WPSF_WpDb {

	/**
	 * @var ICWP_WPSF_WpDb
	 */
	protected static $oInstance = null;

	/**
	 * @var wpdb
	 */
	protected $oWpdb;

	/**
	 * @return ICWP_WPSF_WpDb
	 */
	public static function GetInstance() {
		if ( is_null( self::$oInstance ) ) {
			self::$oInstance = new self;
		}
		return self::$oInstance;
	}

	public function __construct() {
	}

	/**
	 * @param string $sSQL
	 * @return array
	 */
	public function dbDelta( $sSQL ) {
		require_once( ABSPATH.'wp-admin/includes/upgrade.php' );
		return dbDelta( $sSQL );
	}

	/**
	 * @param string $sTable
	 * @param array  $aWhere - delete where (associative array)
	 * @return false|int
	 */
	public function deleteRowsFromTableWhere( $sTable, $aWhere ) {
		return $this->loadWpdb()->delete( $sTable, $aWhere );
	}

	/**
	 * Will completely remove this table from the database
	 * @param string $sTable
	 * @return bool|int
	 */
	public function doDropTable( $sTable ) {
		$sQuery = sprintf( 'DROP TABLE IF EXISTS `%s`', $sTable );
		return $this->doSql( $sQuery );
	}

	/**
	 * Alias for doTruncateTable()
	 * @param string $sTable
	 * @return bool|int
	 */
	public function doEmptyTable( $sTable ) {
		return $this->doTruncateTable( $sTable );
	}

	/**
	 * Given any SQL query, will perform it using the WordPress database object.
	 * @param string $sSqlQuery
	 * @return integer|boolean (number of rows affected or just true/false)
	 */
	public function doSql( $sSqlQuery ) {
		return $this->loadWpdb()->query( $sSqlQuery );
	}

	/**
	 * @param string $sTable
	 * @return bool|int
	 */
	public function doTruncateTable( $sTable ) {
		if ( !$this->getIfTableExists( $sTable ) ) {
			return false;
		}
		$sQuery = sprintf( 'TRUNCATE TABLE `%s`', $sTable );
		return $this->doSql( $sQuery );
	}

	public function getCharCollate() {
		return $this->getWpdb()->get_charset_collate();
	}

	/**
	 * @param string $sTable
	 * @return bool
	 */
	public function getIfTableExists( $sTable ) {
		$sQuery = sprintf( "SHOW TABLES LIKE '%s'", $sTable );
		$mResult = $this->loadWpdb()->get_var( $sQuery );
		return !is_null( $mResult );
	}

	/**
	 * @param string $sTableName
	 * @param string $sArrayMapCallBack
	 * @return array
	 */
	public function getColumnsForTable( $sTableName, $sArrayMapCallBack = '' ) {
		$aColumns = $this->loadWpdb()->get_col( "DESCRIBE ".$sTableName, 0 );

		if ( !empty( $sArrayMapCallBack ) && function_exists( $sArrayMapCallBack ) ) {
			return array_map( $sArrayMapCallBack, $aColumns );
		}
		return $aColumns;
	}

	/**
	 * @param bool $bSiteBase
	 * @return string
	 */
	public function getPrefix( $bSiteBase = true ) {
		$oDb = $this->loadWpdb();
		return $bSiteBase ? $oDb->base_prefix : $oDb->prefix;
	}

	/**
	 * @return string
	 */
	public function getTable_Comments() {
		return $this->loadWpdb()->comments;
	}

	/**
	 * @return string
	 */
	public function getTable_Options() {
		return $this->loadWpdb()->options;
	}

	/**
	 * @return string
	 */
	public function getTable_Posts() {
		return $this->loadWpdb()->posts;
	}

	/**
	 * @return string
	 */
	public function getTable_Users() {
		return $this->loadWpdb()->users;
	}

	/**
	 * @param $sSql
	 * @return null|mixed
	 */
	public function getVar( $sSql ) {
		return $this->loadWpdb()->get_var( $sSql );
	}

	/**
	 * @param string $sTable
	 * @param array  $aData
	 * @return int|false
	 */
	public function insertDataIntoTable( $sTable, $aData ) {
		return $this->loadWpdb()->insert( $sTable, $aData );
	}

	/**
	 * @param string $sTable
	 * @param string $nFormat
	 * @return mixed
	 */
	public function selectAllFromTable( $sTable, $nFormat = ARRAY_A ) {
		$sQuery = sprintf( "SELECT * FROM `%s` WHERE `deleted_at` = 0", $sTable );
		return $this->loadWpdb()->get_results( $sQuery, $nFormat );
	}

	/**
	 * @param string $sQuery
	 * @param        $nFormat
	 * @return array|boolean
	 */
	public function selectCustom( $sQuery, $nFormat = ARRAY_A ) {
		return $this->loadWpdb()->get_results( $sQuery, $nFormat );
	}

	/**
	 * @param string $sQuery
	 * @param string $nTotalCountKey
	 * @return array|boolean
	 */
	public function selectCount( $sQuery, $nTotalCountKey = 'total' ) {
		$nCount = -1;
		$aResult = $this->selectCustom( $sQuery, ARRAY_A );
		if ( is_array( $aResult ) ) {
			$nCount = (int)$aResult[ 0 ][ $nTotalCountKey ];
		}
		return $nCount;
	}

	/**
	 * @param        $sQuery
	 * @param string $nFormat
	 * @return null|object|array
	 */
	public function selectRow( $sQuery, $nFormat = ARRAY_A ) {
		return $this->loadWpdb()->get_row( $sQuery, $nFormat );
	}

	/**
	 * @param string $sTable
	 * @param array  $aData  - new insert data (associative array, column=>data)
	 * @param array  $aWhere - insert where (associative array)
	 * @return integer|boolean (number of rows affected)
	 */
	public function updateRowsFromTableWhere( $sTable, $aData, $aWhere ) {
		return $this->loadWpdb()->update( $sTable, $aData, $aWhere );
	}

	/**
	 * Loads our WPDB object if required.
	 * @return \wpdb
	 */
	protected function loadWpdb() {
		if ( is_null( $this->oWpdb ) ) {
			$this->oWpdb = $this->getWpdb();
		}
		return $this->oWpdb;
	}

	/**
	 */
	private function getWpdb() {
		global $wpdb;
		return $wpdb;
	}
}