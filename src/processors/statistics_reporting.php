<?php

if ( class_exists( 'ICWP_WPSF_Processor_Statistics_Reporting', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'basedb.php' );

class ICWP_WPSF_Processor_Statistics_Reporting extends ICWP_WPSF_BaseDbProcessor {

	/**
	 * @var ICWP_WPSF_Query_Statistics_Consolidation
	 */
	private $oConsolidation;

	/**
	 * @param ICWP_WPSF_FeatureHandler_Statistics $oFeatureOptions
	 */
	public function __construct( ICWP_WPSF_FeatureHandler_Statistics $oFeatureOptions ) {
		parent::__construct( $oFeatureOptions, $oFeatureOptions->getReportingTableName() );
	}

	public function run() {
		$this->setupConsolidationCron();
	}

	public function cron_dailyReportingConsolidation() {
		$this->getConsolidation()
			 ->run();
	}

	protected function setupConsolidationCron() {
		$this->loadWpCronProcessor()
			 ->setRecurrence( 'daily' )
			 ->createCronJob(
				 $this->getCronName(),
				 array( $this, 'cron_dailyReportingConsolidation' )
			 );
		add_action( $this->getFeature()->prefix( 'delete_plugin' ), array( $this, 'deleteCron' ) );
	}

	/**
	 */
	public function deleteCron() {
		$this->loadWpCronProcessor()->deleteCronJob( $this->getCronName() );
	}

	/**
	 * @param string $sStatKey
	 * @return bool
	 */
	protected function query_addNewStatEntry( $sStatKey ) {
		if ( empty( $sStatKey ) || !strpos( $sStatKey, '.' ) ) {
			return false;
		}

		$nColon = strpos( $sStatKey, ':' );
		if ( $nColon !== false ) {
			$sStatKey = substr( $sStatKey, 0, $nColon );
		}

		// Now add new entry
		$mResult = $this->insertData(
			array(
				'stat_key'   => $sStatKey,
				'tally'      => 1,
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
	 * @return ICWP_WPSF_Query_Statistics_Consolidation
	 */
	protected function getConsolidation() {
		if ( !isset( $this->oConsolidation ) ) {
			include( dirname( dirname( __FILE__ ) ).'/query/statistics_consolidation.php' );
			/** @var ICWP_WPSF_FeatureHandler_Statistics $oFO */
			$oFO = $this->getFeature();
			$this->oConsolidation = new ICWP_WPSF_Query_Statistics_Consolidation();
			$this->oConsolidation->setFeature( $oFO );
		}
		return $this->oConsolidation;
	}

	/**
	 * @return string
	 */
	protected function getCronName() {
		$oFO = $this->getFeature();
		return $oFO->prefixOptionKey( $oFO->getDefinition( 'reporting_consolidation_cron_name' ) );
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
				tally int(11) UNSIGNED NOT NULL DEFAULT 1,
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