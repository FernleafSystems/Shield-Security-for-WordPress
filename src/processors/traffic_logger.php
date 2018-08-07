<?php

if ( class_exists( 'ICWP_WPSF_Processor_TrafficLogger', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/basedb.php' );

class ICWP_WPSF_Processor_TrafficLogger extends ICWP_WPSF_BaseDbProcessor {

	/**
	 * @param ICWP_WPSF_Processor_Traffic $oModCon
	 */
	public function __construct( ICWP_WPSF_FeatureHandler_Traffic $oModCon ) {
		parent::__construct( $oModCon, $oModCon->getTrafficTableName() );
		$this->loadAutoload();
	}

	public function run() {
		add_action( 'init', array( $this, 'onWpInit' ) );
		add_action( $this->prefix( 'plugin_shutdown' ), array( $this, 'onWpShutdown' ) );
	}

	public function onWpInit() {
		if ( $this->loadWpUsers()->isUserLoggedIn() ) {
			$oT = $this->getTrafficEntryRetrieve()
					   ->retrieveById( 26 );
		}
	}

	public function onWpShutdown() {
		/** @var ICWP_WPSF_FeatureHandler_Traffic $oFO */
		$oFO = $this->getFeature();

		$bIsLog = $this->getIfLogRequest() && ( $oFO->isLogUsers() || !$this->loadWpUsers()->isUserLoggedIn() );
		if ( $bIsLog ) {
			$this->logTraffic();
		}
	}

	protected function logTraffic() {
		$oDP = $this->loadDP();

		$oEntry = $this->getTrafficEntryVO();
		$oEntry->uid = $this->loadWpUsers()->getCurrentWpUserId();
		$oEntry->ip = inet_pton( $this->ip() );
		$oEntry->verb = $oDP->getRequestMethod();
		$oEntry->path = $oDP->getRequestPath();
		$oEntry->code = http_response_code();
		$oEntry->ref = (string)$oDP->FetchServer( 'HTTP_REFERER' );
		$oEntry->ua = (string)$oDP->FetchServer( 'HTTP_USER_AGENT' );
		$oEntry->payload = json_encode( $this->getRequestPayload() );
		$oEntry->trans = $this->getIfIpTransgressed() ? 1 : 0;

		$this->getTrafficEntryCreator()->create( $oEntry );
	}

	/**
	 * @return array
	 */
	protected function getRequestPayload() {
		$aP = array();
		if ( !empty( $_GET ) ) {
			$aP[ 'get' ] = $_GET;
		}
		if ( !empty( $_POST ) ) {
			$aP[ 'post' ] = $_POST;
		}
		return $aP;
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
			ip varbinary(16) DEFAULT NULL,
			path text NOT NULL DEFAULT '',
			code int(5) NOT NULL DEFAULT '200',
			ref text NOT NULL DEFAULT '',
			ua text NOT NULL DEFAULT '',
			verb varchar(10) NOT NULL DEFAULT 'get',
			payload text NOT NULL DEFAULT '',
			trans tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
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