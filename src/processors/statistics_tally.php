<?php

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Tally;

class ICWP_WPSF_Processor_Statistics_Tally extends ICWP_WPSF_BaseDbProcessor {

	/**
	 * @param ICWP_WPSF_FeatureHandler_Statistics $oModCon
	 */
	public function __construct( ICWP_WPSF_FeatureHandler_Statistics $oModCon ) {
		parent::__construct( $oModCon, $oModCon->getDef( 'statistics_table_name' ) );
	}

	public function run() {
	}

	/**
	 * @return \FernleafSystems\Wordpress\Plugin\Shield\Databases\Tally\Handler
	 */
	protected function createDbHandler() {
		return new \FernleafSystems\Wordpress\Plugin\Shield\Databases\Tally\Handler();
	}

	public function onModuleShutdown() {
		parent::onModuleShutdown();
		if ( !$this->getCon()->isPluginDeleting() ) {
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

		$oDbh = $this->getDbHandler();
		foreach ( $aEntries as $aCollection ) {
			foreach ( $aCollection as $sStatKey => $nTally ) {

				$sParentStatKey = '-';
				if ( strpos( $sStatKey, ':' ) > 0 ) {
					list( $sStatKey, $sParentStatKey ) = explode( ':', $sStatKey, 2 );
				}

				/** @var Tally\Select $oSelect */
				$oSelect = $this->getDbHandler()->getQuerySelector();
				$oStat = $oSelect->retrieveStat( $sStatKey, $sParentStatKey );

				if ( empty( $oStat ) ) {
					/** @var Tally\EntryVO $oStat */
					$oStat = $oDbh->getVo();
					$oStat->stat_key = $sStatKey;
					$oStat->tally = $nTally;
					$oStat->parent_stat_key = $sParentStatKey;
					$oDbh->getQueryInserter()->insert( $oStat );
				}
				else {
					/** @var Tally\Update $oUp */
					$oUp = $oDbh->getQueryUpdater();
					$oUp->incrementTally( $oStat, $nTally );
				}
			}
		}
	}

	/**
	 * @return string
	 */
	protected function getCreateTableSql() {
		return "CREATE TABLE %s (
				id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
				stat_key varchar(100) NOT NULL DEFAULT 0,
				parent_stat_key varchar(100) NOT NULL DEFAULT '',
				tally int(11) UNSIGNED NOT NULL DEFAULT 0,
				created_at int(15) UNSIGNED NOT NULL DEFAULT 0,
				modified_at int(15) UNSIGNED NOT NULL DEFAULT 0,
				deleted_at int(15) UNSIGNED NOT NULL DEFAULT 0,
				PRIMARY KEY  (id)
			) %s;";
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
		/** @var Tally\EntryVO[] $aAll */
		$aAll = $this->getDbHandler()->getQuerySelector()->all();

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

		$oDbh = $this->getDbHandler();
		foreach ( $aKeys as $sKey ) {
			/** @var Tally\EntryVO[] $aAll */
			/** @var Tally\Select $oSel */
			$oSel = $this->getDbHandler()->getQuerySelector();
			$aAll = $oSel->filterByStatKey( $sKey )
						 ->query();
			$oPrimary = array_pop( $aAll );

			$nAdditionalTally = 0;
			foreach ( $aAll as $oTally ) {
				$nAdditionalTally += $oTally->tally;
				$oDbh->getQueryDeleter()->deleteEntry( $oTally );
			}

			/** @var Tally\Update $oUp */
			$oUp = $oDbh->getQueryUpdater();
			$oUp->incrementTally( $oPrimary, $nAdditionalTally );
		}
	}

	/**
	 * override and do not delete
	 */
	public function deleteTable() {
	}

	/**
	 * @deprecated
	 * @return Tally\Update
	 */
	public function getUpdater() {
		return parent::getQueryUpdater();
	}

	/**
	 * @deprecated
	 * @return Tally\Insert
	 */
	public function getQueryInserter() {
		return parent::getQueryInserter();
	}

	/**
	 * @deprecated
	 * @return Tally\Select
	 */
	public function getQuerySelector() {
		return parent::getQuerySelector();
	}
}