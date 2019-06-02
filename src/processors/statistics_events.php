<?php

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Events;

class ICWP_WPSF_Processor_Statistics_Events extends ICWP_WPSF_BaseDbProcessor {

	/**
	 * @var array
	 */
	private $aEvents;

	/**
	 * @param ICWP_WPSF_FeatureHandler_Statistics $oModCon
	 */
	public function __construct( ICWP_WPSF_FeatureHandler_Statistics $oModCon ) {
		parent::__construct( $oModCon, $oModCon->getEventsTableName() );
	}

	public function run() {
		$this->aEvents = [];
		add_action( $this->prefix( 'event' ), function ( $sEvent ) {
			$this->aEvents[] = $sEvent;
		}, 10 );
	}

	public function onModuleShutdown() {
		parent::onModuleShutdown();
		if ( !$this->getCon()->isPluginDeleting() ) {
			$this->commit();
		}
	}

	/**
	 * @return Events\Handler
	 */
	protected function createDbHandler() {
		return new Events\Handler();
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
	private function commit() {
		$oDbh = $this->getDbHandler();
		foreach ( array_unique( $this->aEvents ) as $sEvent ) {
			/** @var Events\EntryVO $oEvt */
			$oEvt = $oDbh->getVo();
			$oEvt->event = $sEvent;
			$oEvt->count = 1;
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