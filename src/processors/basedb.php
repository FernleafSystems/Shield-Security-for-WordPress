<?php

if ( !class_exists( 'ICWP_WPSF_BaseDbProcessor', false ) ):

	require_once( dirname(__FILE__).DIRECTORY_SEPARATOR.'base_wpsf.php' );

	abstract class ICWP_WPSF_BaseDbProcessor extends ICWP_WPSF_Processor_BaseWpsf {

		/**
		 * The full database table name.
		 * @var string
		 */
		protected $sFullTableName;

		/**
		 * @var boolean
		 */
		protected $bTableExists;

		/**
		 * @var integer
		 */
		protected $nAutoExpirePeriod = null;

		/**
		 * @param ICWP_WPSF_FeatureHandler_Base $oFeatureOptions
		 * @param string $sTableName
		 *
		 * @throws Exception
		 */
		public function __construct( $oFeatureOptions, $sTableName = null ) {
			parent::__construct( $oFeatureOptions );
			$this->setTableName( $sTableName );
			$this->createCleanupCron();
			$this->initializeTable();
			add_action( $this->getFeatureOptions()->doPluginPrefix( 'delete_plugin' ), array( $this, 'deleteTable' )  );
		}

		/**
		 * @return bool
		 */
		protected function readyToRun() {
			return ( parent::readyToRun() && $this->getTableExists() );
		}

		/**
		 */
		public function deleteTable() {
			if ( self::getController()->getHasPermissionToManage() && $this->getTableExists() ) {
				$this->deleteCleanupCron();
				$this->loadDbProcessor()->doDropTable( $this->getTableName() );
			}
		}

		/**
		 * @return bool|int
		 */
		protected function createTable() {
			$sSql = $this->getCreateTableSql();
			if ( !empty( $sSql ) ) {
				return $this->loadDbProcessor()->dbDelta( $sSql );
			}
			return true;
		}

		/**
		 */
		protected function initializeTable() {
			if ( $this->getTableExists() ) {
				$oFO = $this->getFeatureOptions();
				if ( $oFO->getOpt( 'recreate_database_table', false ) || !$this->tableIsValid() ) {
					$oFO->setOpt( 'recreate_database_table', false );
					$oFO->savePluginOptions();
					$this->recreateTable();
				}

				$sFullHookName = $this->getDbCleanupHookName();
				add_action( $sFullHookName, array( $this, 'cleanupDatabase' ) );
			}
			else {
				$this->createTable();
			}
		}

		/**
		 * @param array $aData
		 * @return bool|int
		 */
		public function insertData( $aData ) {
			return $this->loadDbProcessor()->insertDataIntoTable( $this->getTableName(), $aData );
		}

		/**
		 * Returns all active, non-deleted rows
		 *
		 * @return array
		 */
		public function selectAll() {
			return $this->query_selectAll();
		}

		/**
		 * @param array $aColumns Leave empty to select all (*) columns
		 * @param bool  $bExcludeDeletedRows
		 * @return array
		 */
		protected function query_selectAll( $aColumns = array(), $bExcludeDeletedRows = true ) {

			// Try to get the database entry that corresponds to this set of data. If we get nothing, fail.
			$sQuery = "SELECT %s FROM `%s` %s";

			$aColumns = $this->validateColumnsParameter( $aColumns );
			$sColumnsSelection = empty( $aColumns ) ? '*' : implode( ',', $aColumns );

			$sQuery = sprintf( $sQuery,
				$sColumnsSelection,
				$this->getTableName(),
				( $bExcludeDeletedRows && $this->getHasColumn( 'deleted_at' ) ) ? "WHERE `deleted_at` = 0" : ''
			);
			$mResult = $this->selectCustom( $sQuery );
			return ( is_array( $mResult ) && isset( $mResult[0] ) ) ? $mResult : array();
		}

		/**
		 * @param string $sQuery
		 * @param $nFormat
		 * @return array|boolean
		 */
		public function selectCustom( $sQuery, $nFormat = ARRAY_A ) {
			return $this->loadDbProcessor()->selectCustom( $sQuery, $nFormat );
		}

		/**
		 * @param array $aData - new insert data (associative array, column=>data)
		 * @param array $aWhere - insert where (associative array)
		 * @return integer|boolean (number of rows affected)
		 */
		public function updateRowsWhere( $aData, $aWhere ) {
			return $this->loadDbProcessor()->updateRowsFromTableWhere( $this->getTableName(), $aData, $aWhere );
		}

		/**
		 * @param integer $nTimeStamp
		 * @return bool|int
		 */
		protected function deleteAllRowsOlderThan( $nTimeStamp ) {
			$sQuery = "
				DELETE from `%s`
				WHERE
					`created_at` < '%s'
			";
			$sQuery = sprintf(
				$sQuery,
				$this->getTableName(),
				esc_sql( $nTimeStamp )
			);
			return $this->loadDbProcessor()->doSql( $sQuery );
		}

		/**
		 * @return string
		 */
		abstract protected function getCreateTableSql();

		/**
		 * Will recreate the whole table
		 */
		public function recreateTable() {
			$this->loadDbProcessor()->doDropTable( $this->getTableName() );
			$this->createTable();
		}

		/**
		 * @return bool
		 */
		protected function tableIsValid() {
			$aColumnsByDefinition = array_map( 'strtolower', $this->getTableColumnsByDefinition() );
			$aActualColumns = $this->loadDbProcessor()->getColumnsForTable( $this->getTableName(), 'strtolower' );
			$bValid = ( count( array_diff( $aActualColumns, $aColumnsByDefinition ) ) <= 0
				&& ( count( array_diff( $aColumnsByDefinition, $aActualColumns ) ) <= 0 ) );
			return $bValid;
		}

		/**
		 * @return array
		 */
		abstract protected function getTableColumnsByDefinition();

		/**
		 * @return string
		 */
		public function getTableName() {
			return $this->sFullTableName;
		}

		/**
		 * @param string $sTableName
		 * @return string
		 * @throws Exception
		 */
		private function setTableName( $sTableName = '' ) {
			if ( empty( $sTableName ) ) {
				throw new Exception( 'Database Table Name is EMPTY' );
			}
			$this->sFullTableName = $this->loadDbProcessor()->getPrefix() . esc_sql( $sTableName );
			return $this->sFullTableName;
		}

		/**
		 * Will setup the cleanup cron to clean out old entries. This should be overridden per implementation.
		 */
		protected function createCleanupCron() {
			$sFullHookName = $this->getDbCleanupHookName();
			if ( ! wp_next_scheduled( $sFullHookName ) && ! defined( 'WP_INSTALLING' ) ) {
				$nNextRun = strtotime( 'tomorrow 6am' ) - get_option( 'gmt_offset' ) * HOUR_IN_SECONDS;
				wp_schedule_event( $nNextRun, 'daily', $sFullHookName );
			}
		}

		/**
		 * Will setup the cleanup cron to clean out old entries. This should be overridden per implementation.
		 */
		protected function deleteCleanupCron() {
			wp_clear_scheduled_hook( $this->getDbCleanupHookName() );
		}

		/**
		 * @param string $sColumnName
		 * @return bool
		 */
		protected function getHasColumn( $sColumnName ) {
			$aColumnsByDefinition = array_map( 'strtolower', $this->getTableColumnsByDefinition() );
			return in_array( $sColumnName, $aColumnsByDefinition );
		}

		/**
		 * @param array $aColumns
		 * @return array
		 */
		protected function validateColumnsParameter( $aColumns ) {
			if ( !empty( $aColumns ) && is_array( $aColumns ) ) {
				$aColumns = array_intersect( $this->getTableColumnsByDefinition(), $aColumns );
			}
			else {
				$aColumns = array();
			}
			return $aColumns;
		}

		/**
		 * @return string
		 */
		protected function getDbCleanupHookName() {
			return $this->getController()->doPluginPrefix( $this->getFeatureOptions()->getFeatureSlug().'_db_cleanup' );
		}

		/**
		 * @return bool|int
		 */
		public function cleanupDatabase() {
			$nAutoExpirePeriod = $this->getAutoExpirePeriod();
			if ( is_null( $nAutoExpirePeriod ) || !$this->getTableExists() ) {
				return false;
			}
			$nTimeStamp = $this->time() - $nAutoExpirePeriod;
			return $this->deleteAllRowsOlderThan( $nTimeStamp );
		}

		/**
		 * @return bool
		 */
		public function getTableExists() {

			// only return true if this is true.
			if ( $this->bTableExists === true ) {
				return true;
			}

			$this->bTableExists = $this->loadDbProcessor()->getIfTableExists( $this->getTableName() );
			return $this->bTableExists;
		}

		/**
		 * 1 in 10 page loads will clean the databases. This ensures that even if the crons don't run
		 * correctly, we'll keep it trim.
		 */
		public function action_doFeatureProcessorShutdown() {
			parent::action_doFeatureProcessorShutdown();
			if ( rand( 1, 10 ) === 1 ) {
				$this->cleanupDatabase();
			}
		}

		/**
		 * @return int
		 */
		protected function getAutoExpirePeriod() {
			return $this->nAutoExpirePeriod;
		}

		/**
		 * @param int $nTimePeriod
		 * @return $this
		 */
		protected function setAutoExpirePeriod( $nTimePeriod ) {
			$this->nAutoExpirePeriod = $nTimePeriod;
			return $this;
		}
	}

endif;