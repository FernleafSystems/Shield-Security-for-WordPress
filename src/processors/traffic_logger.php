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
					   ->retrieveById( 128 );
		}
	}

	public function onWpShutdown() {
		if ( $this->getIfLogRequest() ) {
			$this->logTraffic();
		}
	}

	/**
	 * @return bool
	 */
	protected function getIfLogRequest() {
		/** @var ICWP_WPSF_FeatureHandler_Traffic $oFO */
		$oFO = $this->getFeature();
		return parent::getIfLogRequest()
			   && ( $oFO->isLogUsers() || !$this->loadWpUsers()->isUserLoggedIn() )
			   && !$this->isServiceIp();
	}

	/**
	 * @return bool
	 */
	protected function isServiceIp() {
		$sIp = $this->ip();
		return !$this->loadWpUsers()->isUserLoggedIn() &&
			   (
				   $this->isIp_Statuscake( $sIp )
				   || $this->isIp_Cloudflare( $sIp )
				   || $this->isIp_UptimeRobot( $sIp )
				   || $this->isIp_Pingdom( $sIp )
				   || $this->isIp_GoogleBot( $sIp, (string)$this->loadDP()->FetchServer( 'HTTP_USER_AGENT' ) )
			   );
	}

	/**
	 * @param string $sIp
	 * @return bool
	 */
	protected function isIp_Cloudflare( $sIp ) {
		return $this->loadIpService()->isCloudFlareIp( $sIp );
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
			var_dump( $aIps );
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
		$oEntry->uid = $this->loadWpUsers()->getCurrentWpUserId();
		$oEntry->ip = inet_pton( $this->ip() );
		$oEntry->verb = $oDP->getRequestMethod();
		$oEntry->path = $oDP->getRequestPath();
		$oEntry->code = http_response_code();
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
			verb varchar(10) NOT NULL DEFAULT 'get',
			ua text,
			payload text,
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