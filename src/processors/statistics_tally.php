<?php

if ( class_exists( 'ICWP_WPSF_Processor_Statistics_Tally', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/basedb.php' );

class ICWP_WPSF_Processor_Statistics_Tally extends ICWP_WPSF_BaseDbProcessor {

	/**
	 * @param ICWP_WPSF_FeatureHandler_Statistics $oModCon
	 */
	public function __construct( ICWP_WPSF_FeatureHandler_Statistics $oModCon ) {
		parent::__construct( $oModCon, $oModCon->getStatisticsTableName() );
	}

	public function run() {
	}

	/**
	 * @return ICWP_WPSF_Query_Tally_Delete
	 */
	public function getQueryDeleter() {
		$this->queryRequireLib( 'tally_delete.php' );
		return ( new ICWP_WPSF_Query_Tally_Delete() )->setTable( $this->getTableName() );
	}

	/**
	 * @return ICWP_WPSF_Query_Tally_Insert
	 */
	public function getInserter() {
		$this->queryRequireLib( 'tally_insert.php' );
		return ( new ICWP_WPSF_Query_Tally_Insert() )->setTable( $this->getTableName() );
	}

	/**
	 * @return ICWP_WPSF_Query_Tally_Select
	 */
	public function getSelector() {
		$this->queryRequireLib( 'tally_select.php' );
		return ( new ICWP_WPSF_Query_Tally_Select() )
			->setTable( $this->getTableName() )
			->setResultsAsVo( true )
			->setColumnsDefinition( $this->getTableColumnsByDefinition() );
	}

	/**
	 * @return ICWP_WPSF_Query_Tally_Update
	 */
	public function getUpdater() {
		$this->queryRequireLib( 'tally_update.php' );
		return ( new ICWP_WPSF_Query_Tally_Update() )->setTable( $this->getTableName() );
	}

	public function onModuleShutdown() {
		parent::onModuleShutdown();
		if ( !$this->getMod()->isPluginDeleting() ) {
			$this->commit();
		}
	}

	/**
	 * @return array
	 */
	protected function getTableColumnsByDefinition() {
		$aDef = $this->getMod()->getDef( 'statistics_table_columns' );
		return ( is_array( $aDef ) ? $aDef : array() );
	}

	/**
	 */
	protected function commit() {
		$aEntries = apply_filters( $this->getMod()->prefix( 'collect_stats' ), array() );
		if ( empty( $aEntries ) || !is_array( $aEntries ) ) {
			return;
		}
		foreach ( $aEntries as $aCollection ) {
			foreach ( $aCollection as $sStatKey => $nTally ) {

				$sParentStatKey = '-';
				if ( strpos( $sStatKey, ':' ) > 0 ) {
					list( $sStatKey, $sParentStatKey ) = explode( ':', $sStatKey, 2 );
				}

				$oStat = $this->getSelector()
							  ->retrieveStat( $sStatKey, $sParentStatKey );

				if ( empty( $oStat ) ) {
					$this->getInserter()->insert( $sStatKey, $nTally, $sParentStatKey );
				}
				else {
					$this->getUpdater()->incrementTally( $oStat, $nTally );
				}
			}
		}
	}

	/**
	 * @return string
	 */
	protected function getCreateTableSql() {
		$sSqlTables = "CREATE TABLE %s (
				id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
				stat_key varchar(100) NOT NULL DEFAULT 0,
				parent_stat_key varchar(100) NOT NULL DEFAULT '',
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
	public function cleanupDatabase() {
		$this->consolidateDuplicateKeys();
	}

	/**
	 * Will consolidate multiple rows with the same stat_key into 1 row
	 */
	protected function consolidateDuplicateKeys() {
		/** @var ICWP_WPSF_TallyVO[] $aAll */
		$aAll = $this->getSelector()
					 ->all();

		$aKeys = array();
		foreach ( $aAll as $oTally ) {
			if ( !isset( $aKeys[ $oTally->stat_key ] ) ) {
				$aKeys[ $oTally->stat_key ] = 0;
			}
			$aKeys[ $oTally->stat_key ]++;
		}

		$aKeys = array_keys( array_filter(
			$aKeys,
			function ( $nCount ) {
				return $nCount > 1;
			}
		) );

		foreach ( $aKeys as $sKey ) {
			/** @var ICWP_WPSF_TallyVO[] $aAll */
			$aAll = $this->getSelector()
						 ->filterByStatKey( $sKey )
						 ->query();
			$oPrimary = array_pop( $aAll );

			$nAdditionalTally = 0;
			foreach ( $aAll as $oTally ) {
				$nAdditionalTally += $oTally->tally;
				$this->getQueryDeleter()->deleteById( $oTally->id );
			}

			$this->getUpdater()->incrementTally( $oPrimary, $nAdditionalTally );
		}
	}

	/**
	 * override and do not delete
	 */
	public function deleteTable() {
	}

	/**
	 * @return string
	 */
	protected function queryGetDir() {
		return parent::queryGetDir().'statistics/';
	}
}