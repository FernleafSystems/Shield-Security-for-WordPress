<?php
/**
 * Copyright (c) 2015 iControlWP <support@icontrolwp.com>
 * All rights reserved.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
 * ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

if ( !class_exists('ICWP_WPSF_WpDb_V1') ):

	class ICWP_WPSF_WpDb_V1 {

		/**
		 * @var ICWP_WPSF_WpDb_V1
		 */
		protected static $oInstance = NULL;

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

		public function __construct() {}

		/**
		 * @param string $sTable
		 * @param array $aWhere - delete where (associative array)
		 *
		 * @return false|int
		 */
		public function deleteRowsFromTableWhere( $sTable, $aWhere ) {
			$oDb = $this->loadWpdb();
			return $oDb->delete( $sTable, $aWhere );
		}

		/**
		 * Will completely remove this table from the database
		 *
		 * @param string $sTable
		 *
		 * @return bool|int
		 */
		public function doDropTable( $sTable ) {
			$sQuery = sprintf( 'DROP TABLE IF EXISTS `%s`', $sTable ) ;
			return $this->doSql( $sQuery );
		}

		/**
		 * Alias for doTruncateTable()
		 *
		 * @param string $sTable
		 *
		 * @return bool|int
		 */
		public function doEmptyTable( $sTable ) {
			return $this->doTruncateTable( $sTable );
		}

		/**
		 * Given any SQL query, will perform it using the WordPress database object.
		 *
		 * @param string $sSqlQuery
		 * @return integer|boolean (number of rows affected or just true/false)
		 */
		public function doSql( $sSqlQuery ) {
			$mResult = $this->loadWpdb()->query( $sSqlQuery );
			return $mResult;
		}

		/**
		 * @param string $sTable
		 *
		 * @return bool|int
		 */
		public function doTruncateTable( $sTable ) {
			if ( !$this->getIfTableExists( $sTable ) ) {
				return false;
			}
			$sQuery = sprintf( 'TRUNCATE TABLE `%s`', $sTable );
			return $this->doSql( $sQuery );
		}

		/**
		 * @return bool
		 */
		public function getIfTableExists( $sTable ) {
			$oDb = $this->loadWpdb();
			$sQuery = "SHOW TABLES LIKE '%s'";
			$sQuery = sprintf( $sQuery, $sTable );
			$mResult = $oDb->get_var( $sQuery );
			return !is_null( $mResult );
		}

		/**
		 * @param string $sTableName
		 * @param string $sArrayMapCallBack
		 *
		 * @return array
		 */
		public function getColumnsForTable( $sTableName, $sArrayMapCallBack = '' ) {
			$oDb = $this->loadWpdb();
			$aColumns = $oDb->get_col( "DESCRIBE " . $sTableName, 0 );

			if ( !empty( $sArrayMapCallBack ) && function_exists( $sArrayMapCallBack ) ) {
				return array_map( $sArrayMapCallBack, $aColumns );
			}
			return $aColumns;
		}

		/**
		 * @return string
		 */
		public function getPrefix() {
			$oDb = $this->loadWpdb();
			return $oDb->prefix;
		}

		/**
		 * @return string
		 */
		public function getTable_Comments() {
			$oDb = $this->loadWpdb();
			return $oDb->comments;
		}

		/**
		 * @return string
		 */
		public function getTable_Posts() {
			$oDb = $this->loadWpdb();
			return $oDb->posts;
		}

		/**
		 * @param $sSql
		 *
		 * @return null|mixed
		 */
		public function getVar( $sSql ) {
			return $this->loadWpdb()->get_var( $sSql );
		}

		/**
		 * @param string $sTable
		 * @param array $aData
		 *
		 * @return int|boolean
		 */
		public function insertDataIntoTable( $sTable, $aData ) {
			$oDb = $this->loadWpdb();
			return $oDb->insert( $sTable, $aData );
		}

		/**
		 * @param string $sTable
		 * @param string $nFormat
		 *
		 * @return mixed
		 */
		public function selectAllFromTable( $sTable, $nFormat = ARRAY_A ) {
			$oDb = $this->loadWpdb();
			$sQuery = sprintf( "SELECT * FROM `%s` WHERE `deleted_at` = '0'", $sTable );
			return $oDb->get_results( $sQuery, $nFormat );
		}

		/**
		 * @param string $sQuery
		 * @param $nFormat
		 * @return array|boolean
		 */
		public function selectCustom( $sQuery, $nFormat = ARRAY_A ) {
			$oDb = $this->loadWpdb();
			return $oDb->get_results( $sQuery, $nFormat );
		}

		/**
		 * @param $sQuery
		 * @param string $nFormat
		 *
		 * @return null|object|array
		 */
		public function selectRow( $sQuery, $nFormat = ARRAY_A ) {
			$oDb = $this->loadWpdb();
			return $oDb->get_row( $sQuery, $nFormat );
		}

		/**
		 * @param string $sTable
		 * @param array $aData - new insert data (associative array, column=>data)
		 * @param array $aWhere - insert where (associative array)
		 *
		 * @return integer|boolean (number of rows affected)
		 */
		public function updateRowsFromTableWhere( $sTable, $aData, $aWhere ) {
			$oDb = $this->loadWpdb();
			return $oDb->update( $sTable, $aData, $aWhere );
		}

		/**
		 * Loads our WPDB object if required.
		 *
		 * @return wpdb
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
endif;

if ( !class_exists('ICWP_WPSF_WpDb') ):

	class ICWP_WPSF_WpDb extends ICWP_WPSF_WpDb_V1 {
		/**
		 * @return ICWP_WPSF_WpDb
		 */
		public static function GetInstance() {
			if ( is_null( self::$oInstance ) ) {
				self::$oInstance = new self();
			}
			return self::$oInstance;
		}
	}
endif;