<?php

if ( class_exists( 'ICWP_WPSF_Processor_Traffic', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/basedb.php' );

class ICWP_WPSF_Processor_TrafficLogger extends ICWP_WPSF_BaseDbProcessor {

	/**
	 * @param ICWP_WPSF_Processor_Traffic $oModCon
	 */
	public function __construct( ICWP_WPSF_FeatureHandler_Traffic $oModCon ) {
		parent::__construct( $oModCon, $oModCon->getTrafficTableName() );
	}

	/**
	 * Override to set what this processor does when it's "run"
	 */

	public function run() {
		add_action( 'init', array( $this, 'onWpInit' ) );
		add_action( 'shutdown', array( $this, 'onWpShutdown' ) );
	}

	public function onWpInit() {
		if ( $this->loadWpUsers()->isUserLoggedIn() ) {
		}
	}

	public function onWpShutdown() {
		$this->logTraffic();
	}

	protected function logTraffic() {
		$oUsers = $this->loadWpUsers();
		$oDP = $this->loadDP();

		$oCreator = $this->getTrafficEntryCreator();
		$oEntry = $this->getTrafficEntryVO();

		$oEntry->uid = $oUsers->getCurrentWpUserId();
		$oEntry->ip = inet_pton( $this->ip() );
		$oEntry->verb = $oDP->getRequestMethod();
		$oEntry->path = $oDP->getRequestPath();
		$oEntry->code = http_response_code();
		$oEntry->ref = (string)$oDP->FetchServer( 'HTTP_REFERER' );
		$oEntry->ua = (string)$oDP->FetchServer( 'HTTP_USER_AGENT' );
		$oEntry->payload = $oDP->isMethodPost() ? json_encode( $_POST ) : '';

		$oCreator->create( $oEntry );
	}

	/**
	 * @return ICWP_WPSF_Query_TrafficEntry_Create
	 */
	public function getTrafficEntryCreator() {
		require_once( $this->getQueryDir().'traffic_entry_create.php' );
		return ( new ICWP_WPSF_Query_TrafficEntry_Create() )->setTable( $this->getTableName() );
	}

	/**
	 * @return ICWP_WPSF_Query_TrafficEntry_Retrieve
	 */
	public function getTrafficEntryRetrieve() {
		require_once( $this->getQueryDir().'traffic_entry_retrieve.php' );
		return ( new ICWP_WPSF_Query_TrafficEntry_Retrieve() )
			->setTable( $this->getTableName() )
			->setResultsAsVo( true );
	}

	/**
	 * @return ICWP_WPSF_TrafficEntryVO
	 */
	protected function getTrafficEntryVO() {
		require_once( $this->getQueryDir().'ICWP_WPSF_TrafficEntryVO.php' );
		return new ICWP_WPSF_TrafficEntryVO();
	}

	/**
	 * @return string
	 */
	protected function getCreateTableSql() {
		$sSqlTables = "CREATE TABLE %s (
			id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			uid int(11) UNSIGNED NOT NULL DEFAULT 0,
			ip VARBINARY(16) DEFAULT NULL,
			path text NOT NULL DEFAULT '',
			code int(5) NOT NULL DEFAULT '200',
			ref text NOT NULL DEFAULT '',
			ua text NOT NULL DEFAULT '',
			verb varchar(10) NOT NULL DEFAULT 'get',
			payload text NOT NULL DEFAULT '',
			created_at int(15) UNSIGNED NOT NULL DEFAULT 0,
			deleted_at int(15) UNSIGNED NOT NULL DEFAULT 0,
 			PRIMARY KEY  (id)
		) %s;";
		return sprintf( $sSqlTables, $this->getTableName(), $this->loadDbProcessor()->getCharCollate() );
	}

	/**
	 * @return array
	 */
	protected function getTableColumnsByDefinition() {
		$aDef = $this->getFeature()->getDef( 'traffic_table_columns' );
		return is_array( $aDef ) ? $aDef : array();
	}
}