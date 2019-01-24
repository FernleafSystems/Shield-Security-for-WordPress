<?php

class ICWP_WPSF_Processor_Statistics_Reporting extends ICWP_WPSF_BaseDbProcessor {

	/**
	 * @var ICWP_WPSF_Query_Statistics_Consolidation
	 */
	private $oConsolidation;

	/**
	 * @param ICWP_WPSF_FeatureHandler_Statistics $oModCon
	 */
	public function __construct( ICWP_WPSF_FeatureHandler_Statistics $oModCon ) {
		parent::__construct( $oModCon, $oModCon->getReportingTableName() );
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
		add_action( $this->getMod()->prefix( 'deactivate_plugin' ), array( $this, 'deleteCron' ) );
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

	public function onModuleShutdown() {
		parent::onModuleShutdown();
		if ( !$this->getCon()->isPluginDeleting() ) {
			$this->commit();
		}
	}

	/**
	 * @return ICWP_WPSF_Query_Statistics_Consolidation
	 */
	protected function getConsolidation() {
		if ( !isset( $this->oConsolidation ) ) {
			require_once( $this->queryGetDir().'consolidation.php' );
			/** @var ICWP_WPSF_FeatureHandler_Statistics $oFO */
			$oFO = $this->getMod();
			$this->oConsolidation = new ICWP_WPSF_Query_Statistics_Consolidation();
			$this->oConsolidation->setFeature( $oFO );
		}
		return $this->oConsolidation;
	}

	/**
	 * @return string
	 */
	protected function getCronName() {
		$oFO = $this->getMod();
		return $oFO->prefixOptionKey( $oFO->getDef( 'reporting_consolidation_cron_name' ) );
	}

	/**
	 * @return array
	 */
	protected function getTableColumnsByDefinition() {
		$aDef = $this->getMod()->getDef( 'reporting_table_columns' );
		return ( is_array( $aDef ) ? $aDef : array() );
	}

	/**
	 * @return string
	 */
	protected function getCreateTableSql() {
		return "CREATE TABLE %s (
				id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
				stat_key varchar(100) NOT NULL DEFAULT 0,
				tally int(11) UNSIGNED NOT NULL DEFAULT 1,
				created_at int(15) UNSIGNED NOT NULL DEFAULT 0,
				deleted_at int(15) UNSIGNED NOT NULL DEFAULT 0,
				PRIMARY KEY  (id)
			) %s;";
	}

	/**
	 */
	protected function commit() {
		$aEntries = apply_filters( $this->getMod()->prefix( 'collect_stats' ), array() );
		if ( !empty( $aEntries ) && is_array( $aEntries ) ) {
			foreach ( $aEntries as $aCollection ) {
				foreach ( $aCollection as $sStatKey => $nTally ) {
					$this->query_addNewStatEntry( $sStatKey );
				}
			}
		}
	}

	/**
	 */
	public function deleteTable() {
	} //override and do not delete
}