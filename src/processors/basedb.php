<?php

abstract class ICWP_WPSF_BaseDbProcessor extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 * @var \FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\Handler
	 */
	protected $oDbh;

	/**
	 * @var integer
	 */
	protected $nAutoExpirePeriod = null;

	/**
	 * ICWP_WPSF_BaseDbProcessor constructor.
	 * @param ICWP_WPSF_FeatureHandler_Base $oModCon
	 * @param string                        $sTableName
	 * @throws \Exception
	 */
	public function __construct( $oModCon, $sTableName = null ) {
		parent::__construct( $oModCon );
		$this->initializeTable( $sTableName );
	}

	/**
	 * @param string $sTableName
	 * @throws \Exception
	 */
	protected function initializeTable( $sTableName ) {
		if ( empty( $sTableName ) ) {
			throw new \Exception( 'Table name is empty' );
		}
		$this->getDbHandler()
			 ->setTable( $this->getMod()->prefixOptionKey( $sTableName ) )
			 ->setColumnsDefinition( $this->getTableColumnsByDefinition() )
			 ->setSqlCreate( $this->getCreateTableSql() )
			 ->tableInit();

		add_action( $this->getMod()->prefix( 'delete_plugin' ), array( $this->getDbHandler(), 'deleteTable' ) );
	}

	/**
	 * @return bool
	 */
	public function isReadyToRun() {
		try {
			return ( parent::isReadyToRun() && $this->getDbHandler()->isReady() );
		}
		catch ( \Exception $oE ) {
			return false;
		}
	}

	/**
	 * @return \FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\Handler
	 */
	abstract protected function createDbHandler();

	/**
	 * @return \FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\Handler
	 */
	public function getDbHandler() {
		if ( !isset( $this->oDbh ) ) {
			$this->oDbh = $this->createDbHandler();
		}
		return $this->oDbh;
	}

	/**
	 * @return string
	 */
	abstract protected function getCreateTableSql();

	/**
	 * @return array
	 */
	abstract protected function getTableColumnsByDefinition();

	public function runDailyCron() {
		try {
			if ( $this->getDbHandler()->isReady() ) {
				$this->cleanupDatabase();
			}
		}
		catch ( \Exception $oE ) {
		}
	}

	/**
	 * @return bool|int
	 */
	public function cleanupDatabase() {
		$nAutoExpirePeriod = $this->getAutoExpirePeriod();
		if ( is_null( $nAutoExpirePeriod ) || !$this->getDbHandler()->isTable() ) {
			return false;
		}
		$nTimeStamp = $this->time() - $nAutoExpirePeriod;
		return $this->getDbHandler()->deleteRowsOlderThan( $nTimeStamp );
	}

	/**
	 * 1 in 20 page loads will clean the databases. This ensures that even if the crons don't run
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
	 * @deprecated
	 * @return \FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\Delete
	 */
	protected function getQueryDeleter() {
		return $this->getDbHandler()->getQueryDeleter();
	}

	/**
	 * @deprecated
	 * @return \FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\Insert
	 */
	protected function getQueryInserter() {
		return $this->getDbHandler()->getQueryInserter();
	}

	/**
	 * @deprecated
	 * @return \FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\Select
	 */
	protected function getQuerySelector() {
		return $this->getDbHandler()->getQuerySelector();
	}

	/**
	 * @deprecated
	 * @return \FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\Update
	 */
	protected function getQueryUpdater() {
		return $this->getDbHandler()->getQueryUpdater();
	}
}