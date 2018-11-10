<?php

if ( class_exists( 'ICWP_WPSF_BaseDbProcessor', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base_wpsf.php' );

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
	 * @var bool
	 */
	protected $bTableStructureIsValid;

	/**
	 * @var integer
	 */
	protected $nAutoExpirePeriod = null;

	/**
	 * @param ICWP_WPSF_FeatureHandler_Base $oModCon
	 * @param string                        $sTableName
	 */
	public function __construct( $oModCon, $sTableName = null ) {
		parent::__construct( $oModCon );
		$this->setTableName( $sTableName );
		$this->createCleanupCron();
		$this->initializeTable();
		add_action( $this->getMod()->prefix( 'delete_plugin' ), array( $this, 'deleteTable' ) );
	}

	/**
	 * @return bool
	 */
	public function isReadyToRun() {
		return ( parent::isReadyToRun() && $this->getTableExists() && $this->isTableStructureValid() );
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
			$this->clearTableIsValid();
			return $this->loadDbProcessor()->dbDelta( $sSql );
		}
		return true;
	}

	/**
	 * @return $this
	 */
	protected function initializeTable() {
		if ( $this->getTableExists() ) {
			if ( !$this->isTableStructureValid() ) {
				if ( !$this->isTableStructureValid( true ) ) {
					$this->recreateTable();
				}
			}
			$sFullHookName = $this->getDbCleanupHookName();
			add_action( $sFullHookName, array( $this, 'cleanupDatabase' ) );
		}
		else {
			$this->createTable();
		}
		return $this;
	}

	/**
	 * @param int $nTimeStamp
	 * @return bool
	 */
	protected function deleteRowsOlderThan( $nTimeStamp ) {
		return $this->getQueryDeleter()
					->addWhereOlderThan( $nTimeStamp )
					->query();
	}

	/**
	 * @return ICWP_WPSF_Query_BaseDelete
	 */
	abstract protected function getQueryDeleter();

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
	protected function testTableStructure() {
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
		if ( strpos( $sTableName, $this->prefix( '', '_' ) ) !== 0 ) {
			$sTableName = $this->prefix( $sTableName, '_' );
		}
		$this->sFullTableName = $this->loadDbProcessor()->getPrefix().esc_sql( $sTableName );
		return $this->sFullTableName;
	}

	/**
	 * Will setup the cleanup cron to clean out old entries. This should be overridden per implementation.
	 */
	protected function createCleanupCron() {
		$sFullHookName = $this->getDbCleanupHookName();
		if ( !wp_next_scheduled( $sFullHookName ) && !defined( 'WP_INSTALLING' ) ) {
			$nNextRun = strtotime( 'tomorrow 6am' ) - get_option( 'gmt_offset' )*HOUR_IN_SECONDS;
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
		return $this->getController()->prefix( $this->getMod()->getSlug().'_db_cleanup' );
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
		return $this->deleteRowsOlderThan( $nTimeStamp );
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
	public function onModuleShutdown() {
		parent::onModuleShutdown();
		if ( rand( 1, 20 ) === 2 ) {
			$this->cleanupDatabase();
		}
	}

	/**
	 * @return int
	 */
	protected function getAutoExpirePeriod() {
		return null;
	}

	/**
	 * @return $this
	 */
	protected function clearTableIsValid() {
		unset( $this->bTableStructureIsValid );
		return $this;
	}

	/**
	 * @param bool $bRetest
	 * @return bool
	 */
	protected function isTableStructureValid( $bRetest = false ) {
		if ( $bRetest || !isset( $this->bTableStructureIsValid ) ) {
			$this->bTableStructureIsValid = $this->testTableStructure();
		}
		return $this->bTableStructureIsValid;
	}

	/**
	 * @return string
	 */
	protected function queryGetDir() {
		return dirname( dirname( __FILE__ ) ).'/query/';
	}

	/**
	 * @param string $sFile
	 */
	protected function queryRequireLib( $sFile ) {
		require_once( rtrim( $this->queryGetDir(), '/' ).'/'.$sFile );
	}
}