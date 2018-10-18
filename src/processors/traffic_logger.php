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
	}

	public function run() {
		add_action( $this->prefix( 'plugin_shutdown' ), array( $this, 'onWpShutdown' ) );
	}

	public function onWpShutdown() {
		if ( $this->getIfLogRequest() ) {
			$this->logTraffic();
		}
	}

	public function cleanupDatabase() {
		parent::cleanupDatabase(); // Deletes based on time.
		$this->trimTable();
	}

	protected function trimTable() {
		/** @var ICWP_WPSF_FeatureHandler_Traffic $oFO */
		$oFO = $this->getMod();
		try {
			$this->getQueryDeleter()
				 ->deleteExcess( $oFO->getMaxEntries() );
		}
		catch ( Exception $oE ) {
		}
	}

	/**
	 * @return bool
	 */
	protected function getIfLogRequest() {
		/** @var ICWP_WPSF_FeatureHandler_Traffic $oFO */
		$oFO = $this->getMod();
		$oWp = $this->loadWp();
		$bLoggedIn = $this->loadWpUsers()->isUserLoggedIn();
		return parent::getIfLogRequest()
			   && ( $oFO->getMaxEntries() > 0 )
			   && ( !$this->isCustomExcluded() )
			   && ( $oFO->isIncluded_Simple() || count( $this->loadDP()->getRequestParams( false ) ) > 0 )
			   && ( $oFO->isIncluded_LoggedInUser() || !$bLoggedIn )
			   && ( $oFO->isIncluded_Ajax() || !$oWp->isAjax() )
			   && ( $oFO->isIncluded_Cron() || !$oWp->isCron() )
			   && (
				   $bLoggedIn || // only run these service IP checks if not logged in.
				   (
					   ( $oFO->isIncluded_Search() || !$this->isServiceIp_Search() )
					   && ( $oFO->isIncluded_Uptime() || !$this->isServiceIp_Uptime() )
				   )
			   );
	}

	/**
	 * @return bool
	 */
	protected function isCustomExcluded() {
		/** @var ICWP_WPSF_FeatureHandler_Traffic $oFO */
		$oFO = $this->getMod();
		$oDP = $this->loadDP();
		$aExcls = $oFO->getCustomExclusions();
		$sAgent = (string)$this->loadDP()->server( 'HTTP_USER_AGENT' );
		$sPath = $oDP->getRequestPath().( empty( $_GET ) ? '' : '?'.http_build_query( $_GET ) );

		$bExcluded = false;
		foreach ( $aExcls as $sExcl ) {
			if ( stripos( $sAgent, $sExcl ) !== false || stripos( $sPath, $sExcl ) !== false ) {
				$bExcluded = true;
			}
		}
		return $bExcluded;
	}

	/**
	 * Best to check for logged-in status before using this
	 * @return bool
	 */
	protected function isServiceIp() {
		return ( $this->isServiceIp_Uptime() || $this->isServiceIp_Search() );
	}

	/**
	 * @return bool
	 */
	protected function isServiceIp_Search() {
		$oSP = $this->loadServiceProviders();

		$sIp = $this->ip();
		$sAgent = (string)$this->loadDP()->server( 'HTTP_USER_AGENT' );
		return $oSP->isIp_GoogleBot( $sIp, $sAgent )
			   || $oSP->isIp_BingBot( $sIp, $sAgent )
			   || $oSP->isIp_DuckDuckGoBot( $sIp, $sAgent )
			   || $oSP->isIp_YandexBot( $sIp, $sAgent )
			   || $oSP->isIp_BaiduBot( $sIp, $sAgent )
			   || $oSP->isIp_YahooBot( $sIp, $sAgent )
			   || $oSP->isIp_AppleBot( $sIp, $sAgent );
	}

	/**
	 * @return bool
	 */
	protected function isServiceIp_Uptime() {
		$oSP = $this->loadServiceProviders();

		$sIp = $this->ip();
		$sAgent = (string)$this->loadDP()->server( 'HTTP_USER_AGENT' );
		return $oSP->isIp_Statuscake( $sIp, $sAgent )
			   || $oSP->isIp_UptimeRobot( $sIp, $sAgent )
			   || $oSP->isIp_Pingdom( $sIp, $sAgent );
	}

	protected function logTraffic() {
		$oDP = $this->loadDP();
		$oEntry = $this->getTrafficEntryVO();
		$oEntry->rid = $this->getController()->getShortRequestId();
		$oEntry->uid = $this->loadWpUsers()->getCurrentWpUserId();
		$oEntry->ip = inet_pton( $this->ip() );
		$oEntry->verb = $oDP->getRequestMethod();
		$oEntry->path = $oDP->getRequestPath().( empty( $_GET ) ? '' : '?'.http_build_query( $_GET ) );
		$oEntry->code = http_response_code();
		$oEntry->ua = (string)$oDP->server( 'HTTP_USER_AGENT' );
		$oEntry->trans = $this->getIfIpTransgressed() ? 1 : 0;

		$this->getTrafficInserter()->insert( $oEntry );
	}

	/**
	 * @return ICWP_WPSF_Query_TrafficEntry_Insert
	 */
	public function getTrafficInserter() {
		$this->queryRequireLib( 'insert.php' );
		return ( new ICWP_WPSF_Query_TrafficEntry_Insert() )->setTable( $this->getTableName() );
	}

	/**
	 * @return ICWP_WPSF_Query_TrafficEntry_Count
	 */
	public function getTrafficEntryCounter() {
		$this->queryRequireLib( 'count.php' );
		return ( new ICWP_WPSF_Query_TrafficEntry_Count() )->setTable( $this->getTableName() );
	}

	/**
	 * @return ICWP_WPSF_Query_TrafficEntry_Delete
	 */
	public function getQueryDeleter() {
		$this->queryRequireLib( 'delete.php' );
		return ( new ICWP_WPSF_Query_TrafficEntry_Delete() )->setTable( $this->getTableName() );
	}

	/**
	 * @return ICWP_WPSF_Query_TrafficEntry_Select
	 */
	public function getTrafficEntrySelector() {
		$this->queryRequireLib( 'select.php' );
		return ( new ICWP_WPSF_Query_TrafficEntry_Select() )
			->setTable( $this->getTableName() )
			->setResultsAsVo( true );
	}

	/**
	 * @return ICWP_WPSF_TrafficEntryVO
	 */
	protected function getTrafficEntryVO() {
		$this->queryRequireLib( 'ICWP_WPSF_TrafficEntryVO.php' );
		return new ICWP_WPSF_TrafficEntryVO();
	}

	/**
	 * @return int
	 */
	protected function getAutoExpirePeriod() {
		/** @var ICWP_WPSF_FeatureHandler_Traffic $oFO */
		$oFO = $this->getMod();
		return $oFO->getAutoCleanDays()*DAY_IN_SECONDS;
	}

	/**
	 * @return string
	 */
	protected function getCreateTableSql() {
		$sSqlTables = "CREATE TABLE %s (
			id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			rid varchar(10) NOT NULL DEFAULT '',
			uid int(11) UNSIGNED NOT NULL DEFAULT 0,
			ip varbinary(16) DEFAULT NULL,
			path text NOT NULL DEFAULT '',
			code int(5) NOT NULL DEFAULT '200',
			verb varchar(10) NOT NULL DEFAULT 'get',
			ua text,
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
		$aDef = $this->getMod()->getDef( 'traffic_table_columns' );
		return is_array( $aDef ) ? $aDef : array();
	}

	/**
	 * @return string
	 */
	protected function queryGetDir() {
		return parent::queryGetDir().'traffic/';
	}
}