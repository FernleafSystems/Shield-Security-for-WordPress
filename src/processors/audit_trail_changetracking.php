<?php

use FernleafSystems\Wordpress\Plugin\Shield\ChangeTrack;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\ChangeTracking;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_AuditTrail_ChangeTracking extends ICWP_WPSF_BaseDbProcessor {

	/**
	 * @param ICWP_WPSF_FeatureHandler_AuditTrail $oModCon
	 * @throws \Exception
	 */
	public function __construct( ICWP_WPSF_FeatureHandler_AuditTrail $oModCon ) {
		parent::__construct( $oModCon, $oModCon->getDef( 'table_name_changetracking' ) );
	}

	public function runHourlyCron() {
		/** @var ICWP_WPSF_FeatureHandler_AuditTrail $oFO */
		$oFO = $this->getMod();
		if ( $oFO->isCTSnapshotDue() && $this->isReadyToRun() ) {
			$oFO->updateCTLastSnapshotAt();
			$this->runSnapshot();
		}
	}

	/**
	 * @return ChangeTracking\EntryVO
	 */
	private function buildSnapshot() {
		$oVo = new ChangeTracking\EntryVO();
		$oVo->data = ( new ChangeTrack\Snapshot\Collate() )->run();
		$oVo->meta = [
			'ts'      => Services::Request()->ts(),
			'version' => $this->getCon()->getVersion(),
		];
		return $oVo;
	}

	/**
	 * Should always use the appropriate VO to set the data and pass to insert()
	 * This ensures correct encoding of data for DB storage.
	 *
	 * Also, wherever this snapshot is run from, should update the "last snapshot at" as appropriate
	 * @return ChangeTracking\EntryVO
	 */
	private function runSnapshot() {
		$oVo = $this->buildSnapshot();
		/** @var ChangeTracking\Insert $oInsert */
		$oInsert = $this->getDbHandler()->getQueryInserter();
		$oInsert->insert( $oVo );
		return $oVo;
	}

	public function cleanupDatabase() {
		parent::cleanupDatabase(); // Deletes based on time.
		$this->trimTable();
	}

	/**
	 * ABstract this and move it into base DB class
	 */
	protected function trimTable() {
		/** @var ICWP_WPSF_FeatureHandler_AuditTrail $oFO */
		$oFO = $this->getMod();
		try {
			$this->getDbHandler()
				 ->getQueryDeleter()
				 ->deleteExcess( $oFO->getCTMaxSnapshots() );
		}
		catch ( \Exception $oE ) {
		}
	}

	/**
	 */
	public function run() {
		if ( !$this->isReadyToRun() ) {
			return;
		}
	}

	/**
	 * @return string
	 */
	protected function getCreateTableSql() {
		return "CREATE TABLE %s (
			id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			data BLOB NOT NULL DEFAULT '' COMMENT 'Snapshot Data',
			meta TEXT NOT NULL DEFAULT '' COMMENT 'Snapshot Meta',
			created_at int(15) UNSIGNED NOT NULL DEFAULT 0,
			deleted_at int(15) UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY  (id)
		) %s;";
	}

	/**
	 * @return array
	 */
	protected function getTableColumnsByDefinition() {
		$aDef = $this->getMod()->getDef( 'table_columns_changetracking' );
		return ( is_array( $aDef ) ? $aDef : [] );
	}

	/**
	 * @return ChangeTracking\Handler
	 */
	protected function createDbHandler() {
		return new ChangeTracking\Handler();
	}
}