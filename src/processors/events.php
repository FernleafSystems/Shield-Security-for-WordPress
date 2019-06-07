<?php

use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\Events;

class ICWP_WPSF_Processor_Events extends ICWP_WPSF_BaseDbProcessor {

	/**
	 * @var bool
	 */
	private $bStat = false;

	/**
	 * @param ICWP_WPSF_FeatureHandler_Events $oModCon
	 */
	public function __construct( ICWP_WPSF_FeatureHandler_Events $oModCon ) {
		parent::__construct( $oModCon, $oModCon->getEventsTableName() );
	}

	public function run() {
		$this->bStat = true;
	}

	public function onModuleShutdown() {
		parent::onModuleShutdown();
		if ( $this->bStat && !$this->getCon()->isPluginDeleting() ) {
			$this->commitEvents();
		}
	}

	/**
	 * @return Events\Handler
	 */
	protected function createDbHandler() {
		/** @var ICWP_WPSF_FeatureHandler_Events $oMod */
		$oMod = $this->getMod();
		return $oMod->getDbHandler();
	}

	/**
	 * @return array
	 */
	protected function getTableColumnsByDefinition() {
		$aDef = $this->getMod()->getDef( 'events_table_columns' );
		return is_array( $aDef ) ? $aDef : [];
	}

	/**
	 */
	private function commitEvents() {
		/** @var ICWP_WPSF_FeatureHandler_Events $oMod */
		$oMod = $this->getMod();
		$oDbh = $this->getDbHandler();
		foreach ( $oMod->getRegisteredEvents() as $sEvent => $nTs ) {
			/** @var Events\EntryVO $oEvt */
			$oEvt = $oDbh->getVo();
			$oEvt->event = $sEvent;
			$oEvt->count = 1;
			$oEvt->created_at = empty( $nTs ) ? Services::Request()->ts() : $nTs;
			/** @var Events\Insert $oQI */
			$oQI = $oDbh->getQueryInserter();
			$oQI->insert( $oEvt );
		}
	}

	/**
	 * @return string
	 */
	protected function getCreateTableSql() {
		return "CREATE TABLE %s (
				id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
				event varchar(50) NOT NULL DEFAULT 'none' COMMENT 'Event ID',
				count int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Total',
				created_at int(15) UNSIGNED NOT NULL DEFAULT 0,
				deleted_at int(15) UNSIGNED NOT NULL DEFAULT 0,
				PRIMARY KEY  (id)
			) %s;";
	}

	/**
	 */
	public function cleanupDatabase() {
//		$this->consolidateDuplicateKeys();
	}
}