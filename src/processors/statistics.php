<?php

if ( !class_exists('ICWP_WPSF_Processor_Statistics') ):

	require_once( dirname(__FILE__).DIRECTORY_SEPARATOR.'basedb.php' );

	class ICWP_WPSF_Processor_Statistics extends ICWP_WPSF_BaseDbProcessor {
		/**
		 * @param ICWP_WPSF_FeatureHandler_Statistics $oFeatureOptions
		 */
		public function __construct( ICWP_WPSF_FeatureHandler_Statistics $oFeatureOptions ) {
			parent::__construct( $oFeatureOptions, $oFeatureOptions->getStatisticsTableName() );
		}

		public function run() {}

		/**
		 * @param string $sStatKey
		 * @param int $nTally
		 * @return bool|int
		 */
		protected function query_addNewStatEntry( $sStatKey, $nTally ) {

			// Now add new entry
			$aNewData = array();
			$aNewData[ 'statKey' ]			= $sStatKey;
			$aNewData[ 'tally' ]			= $nTally;
			$aNewData[ 'modified_at' ]		= $this->time();
			$aNewData[ 'created_at' ]		= $this->time();

			$mResult = $this->insertData( $aNewData );
			return $mResult ? $aNewData : $mResult;
		}

		/**
		 * @param string $sStatKey
		 * @param int $nNewTally
		 * @return bool|int
		 */
		protected function query_updateTallyForStat( $sStatKey, $nNewTally ) {
			$aCurrentData = array(
				'statkey'	=> $sStatKey
			);
			$aUpdated = array(
				'tally'			=> $nNewTally,
				'modified_at'	=> $this->time()
			);
			return $this->updateRowsWhere( $aUpdated, $aCurrentData );
		}

		protected function query_getStatData( $sStatKey ) {

			$sStatKey = esc_sql( $sStatKey ); //just in-case someones tries to get all funky up in it

			// Try to get the database entry that corresponds to this set of data. If we get nothing, fail.
			$sQuery = "
				SELECT *
					FROM `%s`
				WHERE
					`statkey`			= '%s'
					AND `deleted_at`	= '0'
			";
			$sQuery = sprintf( $sQuery,
				$this->getTableName(),
				$sStatKey
			);
			$mResult = $this->selectCustom( $sQuery );
			return ( is_array( $mResult ) && isset( $mResult[0] ) ) ? $mResult[0] : array();
		}

		public function action_doFeatureProcessorShutdown () {
			parent::action_doFeatureProcessorShutdown();
			if ( ! $this->getFeatureOptions()->getIsPluginDeleting() ) {
				$this->commit();
			}
		}

		/**
		 * @return string
		 */
		protected function getTableColumnsByDefinition() {
			return $this->getFeatureOptions()->getDefinition( 'statistics_table_columns' );
		}

		/**
		 * @return string
		 */
		protected function getCreateTableSql() {
			$sSqlTables = "CREATE TABLE %s (
				id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
				statkey varchar(100) NOT NULL DEFAULT 0,
				tally int(11) UNSIGNED NOT NULL DEFAULT 0,
				created_at int(15) UNSIGNED NOT NULL DEFAULT 0,
				modified_at int(15) UNSIGNED NOT NULL DEFAULT 0,
				deleted_at int(15) UNSIGNED NOT NULL DEFAULT 0,
				PRIMARY KEY  (id)
			) %s;";
			return sprintf( $sSqlTables, $this->getTableName(), $this->loadDbProcessor()->getCharCollate() );
		}

		/**
		 */
		protected function commit() {
			$aEntries = apply_filters( $this->getFeatureOptions()->doPluginPrefix( 'collect_stats' ), array() );
			if ( empty( $aEntries ) || !is_array( $aEntries ) ) {
				return;
			}
			foreach( $aEntries as $aCollection ) {
				foreach( $aCollection as $sStatKey => $nTally ) {

					$aCurrentData = $this->query_getStatData( $sStatKey );
					if ( empty( $aCurrentData ) ) {
						$this->query_addNewStatEntry( $sStatKey, $nTally );
					}
					else {
						$this->query_updateTallyForStat( $sStatKey, $aCurrentData[ 'tally' ] + $nTally );
					}
					
				}
			}
		}
	}

endif;