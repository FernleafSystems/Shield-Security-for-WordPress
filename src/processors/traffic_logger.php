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
		$oFO = $this->getFeature();
		try {
			$this->getTrafficEntryDeleter()
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
		$oFO = $this->getFeature();
		$oWp = $this->loadWp();
		$bLoggedIn = $this->loadWpUsers()->isUserLoggedIn();
		return parent::getIfLogRequest()
			   && ( $oFO->getMaxEntries() > 0 )
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
	 * Best to check for logged-in status before using this
	 * @return bool
	 */
	protected function isServiceIp() {
		return ( $this->isServiceIp_Uptime() || $this->isServiceIp_Search() );
	}

	/**
	 * TODO: Other search engines
	 * @return bool
	 */
	protected function isServiceIp_Search() {
		$sIp = $this->ip();
		return $this->isIp_GoogleBot( $sIp, (string)$this->loadDP()->FetchServer( 'HTTP_USER_AGENT' ) );
	}

	/**
	 * @return bool
	 */
	protected function isServiceIp_Uptime() {
		$sIp = $this->ip();
		return $this->isIp_Statuscake( $sIp )
			   || $this->isIp_UptimeRobot( $sIp )
			   || $this->isIp_Pingdom( $sIp );
	}

	/**
	 * @param string $sIp
	 * @return bool
	 */
	protected function isIp_Cloudflare( $sIp ) {
		return $this->loadIpService()->isCloudFlareIp( $sIp );
	}

	/**
	 * @param string $sIp
	 * @param string $sUserAgent
	 * @return bool
	 */
	protected function isIp_BingBot( $sIp, $sUserAgent ) {
		$oWp = $this->loadWp();

		$aIps = $oWp->getTransient( $this->prefix( 'serviceips_bingbot' ) );
		if ( !is_array( $aIps ) ) {
			$aIps = array();
		}

		if ( !in_array( $sIp, $aIps ) && $this->loadIpService()->isIpBingBot( $sIp, $sUserAgent ) ) {
			$aIps[] = $sIp;
			$aIps = $oWp->setTransient( $this->prefix( 'serviceips_bingbot' ), $aIps, WEEK_IN_SECONDS*4 );
		}

		return in_array( $sIp, $aIps );
	}

	/**
	 * https://support.google.com/webmasters/answer/80553?hl=en
	 * @param string $sIp
	 * @param string $sUserAgent
	 * @return bool
	 */
	protected function isIp_GoogleBot( $sIp, $sUserAgent ) {
		$oWp = $this->loadWp();

		$aIps = $oWp->getTransient( $this->prefix( 'serviceips_googlebot' ) );
		if ( !is_array( $aIps ) ) {
			$aIps = array();
		}

		if ( !in_array( $sIp, $aIps ) && $this->loadIpService()->isIpGoogleBot( $sIp, $sUserAgent ) ) {
			$aIps[] = $sIp;
			$aIps = $oWp->setTransient( $this->prefix( 'serviceips_googlebot' ), $aIps, WEEK_IN_SECONDS*4 );
		}

		return in_array( $sIp, $aIps );
	}

	/**
	 * @param string $sIp
	 * @return bool
	 */
	protected function isIp_Statuscake( $sIp ) {
		return in_array( $sIp, $this->getIpsStatuscake() );
	}

	/**
	 * @param string $sIp
	 * @return bool
	 */
	protected function isIp_Pingdom( $sIp ) {
		return in_array( $sIp, $this->getIpsPingdom()[ $this->loadIpService()->getIpVersion( $sIp ) ] );
	}

	/**
	 * @param string $sIp
	 * @return bool
	 */
	protected function isIp_UptimeRobot( $sIp ) {
		return in_array( $sIp, $this->getIpsUptimeRobot()[ $this->loadIpService()->getIpVersion( $sIp ) ] );
	}

	/**
	 * @return string[]
	 */
	protected function getIpsStatuscake() {
		$oWp = $this->loadWp();
		$aIps = $oWp->getTransient( $this->prefix( 'serviceips_statuscake' ) );
		if ( empty( $aIps ) ) {
			$aIps = $this->loadIpService()->getServiceIps_StatusCake();
			$oWp->setTransient( $this->prefix( 'serviceips_statuscake' ), $aIps, WEEK_IN_SECONDS*4 );
		}
		return $aIps;
	}

	/**
	 * @return array[]
	 */
	protected function getIpsUptimeRobot() {
		$oWp = $this->loadWp();
		$aIps = $oWp->getTransient( $this->prefix( 'serviceips_uptimerobot' ) );
		if ( empty( $aIps ) ) {
			$aIps = array(
				4 => $this->loadIpService()->getServiceIps_UptimeRobot( 4 ),
				6 => $this->loadIpService()->getServiceIps_UptimeRobot( 6 )
			);
			$oWp->setTransient( $this->prefix( 'serviceips_uptimerobot' ), $aIps, WEEK_IN_SECONDS*4 );
		}
		return $aIps;
	}

	/**
	 * @return array[]
	 */
	protected function getIpsPingdom() {
		$oWp = $this->loadWp();
		$aIps = $oWp->getTransient( $this->prefix( 'serviceips_pingdom' ) );
		if ( empty( $aIps ) ) {
			$aIps = array(
				4 => $this->loadIpService()->getServiceIps_Pingdom( 4 ),
				6 => $this->loadIpService()->getServiceIps_Pingdom( 6 )
			);
			$oWp->setTransient( $this->prefix( 'serviceips_pingdom' ), $aIps, WEEK_IN_SECONDS*4 );
		}
		return $aIps;
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
		$oEntry->ua = (string)$oDP->FetchServer( 'HTTP_USER_AGENT' );
		$oEntry->trans = $this->getIfIpTransgressed() ? 1 : 0;

		$this->getTrafficEntryCreator()->create( $oEntry );
	}

	/**
	 * @return ICWP_WPSF_Query_TrafficEntry_Insert
	 */
	public function getTrafficEntryCreator() {
		require_once( $this->getQueryDir().'traffic_entry_insert.php' );
		return ( new ICWP_WPSF_Query_TrafficEntry_Insert() )->setTable( $this->getTableName() );
	}

	/**
	 * @return ICWP_WPSF_Query_TrafficEntry_Count
	 */
	public function getTrafficEntryCounter() {
		require_once( $this->getQueryDir().'traffic_entry_count.php' );
		return ( new ICWP_WPSF_Query_TrafficEntry_Count() )->setTable( $this->getTableName() );
	}

	/**
	 * @return ICWP_WPSF_Query_TrafficEntry_Delete
	 */
	public function getTrafficEntryDeleter() {
		require_once( $this->getQueryDir().'traffic_entry_delete.php' );
		return ( new ICWP_WPSF_Query_TrafficEntry_Delete() )->setTable( $this->getTableName() );
	}

	/**
	 * @return ICWP_WPSF_Query_TrafficEntry_Select
	 */
	public function getTrafficEntrySelector() {
		require_once( $this->getQueryDir().'traffic_entry_select.php' );
		return ( new ICWP_WPSF_Query_TrafficEntry_Select() )
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
	 * @return int
	 */
	protected function getAutoExpirePeriod() {
		/** @var ICWP_WPSF_FeatureHandler_Traffic $oFO */
		$oFO = $this->getFeature();
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
		$aDef = $this->getFeature()->getDef( 'traffic_table_columns' );
		return is_array( $aDef ) ? $aDef : array();
	}
}