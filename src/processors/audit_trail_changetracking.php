<?php

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
				 ->deleteExcess( $oFO->getMaxEntries() );
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
		/** @var ICWP_WPSF_FeatureHandler_AuditTrail $oFO */
		$oFO = $this->getMod();
	}

	/**
	 * @return string
	 */
	protected function getCreateTableSql() {
		return "CREATE TABLE %s (
			id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			rid varchar(10) NOT NULL DEFAULT '' COMMENT 'Request ID',
			ip varchar(40) NOT NULL DEFAULT 0 COMMENT 'Visitor IP Address',
			wp_username varchar(255) NOT NULL DEFAULT 'none' COMMENT 'WP User',
			context varchar(32) NOT NULL DEFAULT 'none' COMMENT 'Audit Context',
			event varchar(50) NOT NULL DEFAULT 'none' COMMENT 'Specific Audit Event',
			category int(3) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Severity',
			message text COMMENT 'Audit Event Description',
			meta text COMMENT 'Audit Event Data',
			immutable tinyint(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'May Be Deleted',
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
		return ( is_array( $aDef ) ? $aDef : array() );
	}

	/**
	 * @return ChangeTracking\Handler
	 */
	protected function createDbHandler() {
		return new ChangeTracking\Handler();
	}
}