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
	 * @return ICWP_WPSF_Query_Statistics_Insert
	 */
	public function getInsertor() {
		require_once( $this->getQueryDir().'tally_insert.php' );
		return ( new ICWP_WPSF_Query_Statistics_Insert() )->setTable( $this->getTableName() );
	}

	/**
	 * @return ICWP_WPSF_Query_Statistics_Update
	 */
	public function getUpdater() {
		require_once( $this->getQueryDir().'tally_update.php' );
		return ( new ICWP_WPSF_Query_Statistics_Update() )->setTable( $this->getTableName() );
	}

	/**
	 * @return ICWP_WPSF_Query_Statistics_Select
	 */
	public function getSelector() {
		require_once( $this->getQueryDir().'tally_select.php' );
		return ( new ICWP_WPSF_Query_Statistics_Select() )
			->setTable( $this->getTableName() )
			->setResultsAsVo( true );
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
					$this->getInsertor()->insert( $sStatKey, $nTally, $sParentStatKey );
				}
				else {
					$this->getUpdater()->updateTally( $oStat, $nTally );
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
	 * override and do not delete
	 */
	public function deleteTable() {
	}

	/**
	 * @return string
	 */
	protected function getQueryDir() {
		return parent::getQueryDir().'statistics/';
	}
}