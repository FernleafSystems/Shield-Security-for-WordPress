<?php

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Traffic;

class ICWP_WPSF_Processor_TrafficLogger extends ICWP_WPSF_BaseDbProcessor {

	/**
	 * @param ICWP_WPSF_Processor_Traffic $oModCon
	 */
	public function __construct( ICWP_WPSF_FeatureHandler_Traffic $oModCon ) {
		parent::__construct( $oModCon, $oModCon->getDef( 'traffic_table_name' ) );
	}

	public function onModuleShutdown() {
		if ( $this->getIfLogRequest() ) {
			$this->logTraffic();
		}
		parent::onModuleShutdown();
	}

	public function cleanupDatabase() {
		parent::cleanupDatabase(); // Deletes based on time.
		$this->trimTable();
	}

	protected function trimTable() {
		/** @var ICWP_WPSF_FeatureHandler_Traffic $oFO */
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
	 * @return bool
	 */
	protected function getIfLogRequest() {
		/** @var ICWP_WPSF_FeatureHandler_Traffic $oFO */
		$oFO = $this->getMod();
		$oWp = $this->loadWp();
		$bLoggedIn = $this->loadWpUsers()->isUserLoggedIn();
		return parent::getIfLogRequest()
			   && !$this->getCon()->isPluginDeleting()
			   && ( $oFO->getMaxEntries() > 0 )
			   && ( !$this->isCustomExcluded() )
			   && ( $oFO->isIncluded_Simple() || count( $this->loadRequest()->getParams( false ) ) > 0 )
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
		$oReq = $this->loadRequest();

		$sAgent = $oReq->getUserAgent();
		$sPath = $oReq->getPath().( empty( $_GET ) ? '' : '?'.http_build_query( $_GET ) );

		$bExcluded = false;
		foreach ( $oFO->getCustomExclusions() as $sExcl ) {
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
		$sAgent = (string)$this->loadRequest()->server( 'HTTP_USER_AGENT' );
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
		$sAgent = (string)$this->loadRequest()->server( 'HTTP_USER_AGENT' );
		return $oSP->isIp_Statuscake( $sIp, $sAgent )
			   || $oSP->isIp_UptimeRobot( $sIp, $sAgent )
			   || $oSP->isIp_Pingdom( $sIp, $sAgent );
	}

	protected function logTraffic() {
		$oReq = $this->loadRequest();

		// For multisites that are separated by sub-domains we also show the host.
		$sLeadingPath = $this->loadWp()->isMultisite_SubdomainInstall() ? $oReq->getHost() : '';

		/** @var Traffic\EntryVO $oEntry */
		$oEntry = $this->getDbHandler()->getVo();

		$oEntry->rid = $this->getCon()->getShortRequestId();
		$oEntry->uid = $this->loadWpUsers()->getCurrentWpUserId();
		$oEntry->ip = inet_pton( $this->ip() );
		$oEntry->verb = $oReq->getMethod();
		$oEntry->path = $sLeadingPath.$oReq->getPath().( empty( $_GET ) ? '' : '?'.http_build_query( $_GET ) );
		$oEntry->code = http_response_code();
		$oEntry->ua = $oReq->getUserAgent();
		$oEntry->trans = $this->getIfIpTransgressed() ? 1 : 0;

		$this->getDbHandler()
			 ->getQueryInserter()
			 ->insert( $oEntry );
	}

	/**
	 * @return \FernleafSystems\Wordpress\Plugin\Shield\Databases\Traffic\Handler
	 */
	protected function createDbHandler() {
		return new \FernleafSystems\Wordpress\Plugin\Shield\Databases\Traffic\Handler();
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
		return "CREATE TABLE %s (
			id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			rid varchar(10) NOT NULL DEFAULT '' COMMENT 'Request ID',
			uid int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'User ID',
			ip varbinary(16) DEFAULT NULL COMMENT 'Visitor IP Address',
			path text NOT NULL DEFAULT '' COMMENT 'Request Path or URI',
			code int(5) NOT NULL DEFAULT '200' COMMENT 'HTTP Response Code',
			verb varchar(10) NOT NULL DEFAULT 'get' COMMENT 'HTTP Method',
			ua text COMMENT 'Browser User Agent String',
			trans tinyint(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Trangression',
			created_at int(15) UNSIGNED NOT NULL DEFAULT 0,
			deleted_at int(15) UNSIGNED NOT NULL DEFAULT 0,
 			PRIMARY KEY  (id)
		) %s;";
	}

	/**
	 * @return array
	 */
	protected function getTableColumnsByDefinition() {
		$aDef = $this->getMod()->getDef( 'traffic_table_columns' );
		return is_array( $aDef ) ? $aDef : array();
	}

	/**
	 * @deprecated
	 * @return Traffic\Insert
	 */
	public function getQueryInserter() {
		return parent::getQueryInserter();
	}

	/**
	 * @deprecated
	 * @return Traffic\Select
	 */
	public function getQuerySelector() {
		return parent::getQuerySelector();
	}
}