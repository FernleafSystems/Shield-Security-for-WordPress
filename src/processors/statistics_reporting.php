<?php

if ( class_exists( 'ICWP_WPSF_Processor_Statistics_Reporting', false ) ) {
	return;
}

require_once( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'basedb.php' );

class ICWP_WPSF_Processor_Statistics_Reporting extends ICWP_WPSF_BaseDbProcessor {

	/**
	 * @param ICWP_WPSF_FeatureHandler_Statistics $oFeatureOptions
	 */
	public function __construct( ICWP_WPSF_FeatureHandler_Statistics $oFeatureOptions ) {
		parent::__construct( $oFeatureOptions, $oFeatureOptions->getReportingTableName() );
	}

	public function run() { }

	/**
	 * @param string $sStatKey
	 * @return bool
	 */
	protected function query_addNewStatEntry( $sStatKey ) {
		if ( empty( $sStatKey ) || !strpos( $sStatKey, '.' ) ) {
			return false;
		}

		// Now add new entry
		$mResult = $this->insertData(
			array(
				'stat_key' => $sStatKey,
				'created_at' => $this->time(),
				'deleted_at' => 0,
			)
		);
		return (bool)$mResult;
	}

	public function action_doFeatureProcessorShutdown() {
		parent::action_doFeatureProcessorShutdown();
		if ( !$this->getFeature()->isPluginDeleting() ) {
			$this->commit();
		}
	}

	/**
	 * @return array
	 */
	protected function getTableColumnsByDefinition() {
		$aDef = $this->getFeature()->getDefinition( 'reporting_table_columns' );
		return ( is_array( $aDef ) ? $aDef : array() );
	}

	/**
	 * @return string
	 */
	protected function getCreateTableSql() {
		$sSqlTables = "CREATE TABLE %s (
				id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
				stat_key varchar(100) NOT NULL DEFAULT 0,
				created_at int(15) UNSIGNED NOT NULL DEFAULT 0,
				deleted_at int(15) UNSIGNED NOT NULL DEFAULT 0,
				PRIMARY KEY  (id)
			) %s;";
		return sprintf( $sSqlTables, $this->getTableName(), $this->loadDbProcessor()->getCharCollate() );
	}

	/**
	 */
	protected function commit() {
		$aEntries = apply_filters( $this->getFeature()->prefix( 'collect_stats' ), array() );
		if ( !empty( $aEntries ) && is_array( $aEntries ) ) {
			foreach ( $aEntries as $aCollection ) {
				foreach ( $aCollection as $sStatKey => $nTally ) {
					$this->query_addNewStatEntry( $sStatKey );
				}
			}
		}
	}

	/**
	 * @return array|bool
	 */
	protected function query_deleteInvalidStatKeys() {
		$sQuery = "
				DELETE FROM `%s`
				WHERE `stat_key` NOT LIKE '%%.%%'
			";
		return $this->selectCustom( sprintf( $sQuery, $this->getTableName() ) );
	}

	/**
	 * We override this to clean out any strange statistics entries (Human spam words mostly)
	 * @return bool|int
	 */
	public function cleanupDatabase() {
		parent::cleanupDatabase();
		return $this->query_deleteInvalidStatKeys();
	}

	/**
	 */
	public function deleteTable() {
	} //override and do not delete
}