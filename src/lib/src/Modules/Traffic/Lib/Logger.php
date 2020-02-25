<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Traffic\EntryVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic;
use FernleafSystems\Wordpress\Services\Services;

class Logger {

	use ModConsumer;

	public function run() {
		/** @var Traffic\Options $oOpts */
		$oOpts = $this->getOptions();
		if ( $oOpts->isTrafficLoggerEnabled() ) {
			add_action( $this->getCon()->prefix( 'plugin_shutdown' ), function () {
				if ( $this->isRequestToBeLogged() ) {
					$this->logTraffic();
				}
			} );
		}
	}

	/**
	 * @return bool
	 */
	private function isRequestToBeLogged() {
		return apply_filters( $this->getCon()->prefix( 'is_log_traffic' ), true )
			   && !$this->getCon()->isPluginDeleting()
			   && ( !$this->isCustomExcluded() )
			   && ( !$this->isRequestTypeExcluded() );
	}

	/**
	 * @return bool
	 */
	private function isRequestTypeExcluded() {
		/** @var Traffic\Options $oOpts */
		$oOpts = $this->getOptions();
		$aEx = $oOpts->getReqTypeExclusions();
		$bLoggedIn = Services::WpUsers()->isUserLoggedIn();

		$bExcluded = ( in_array( 'simple', $aEx ) && count( Services::Request()->getRawRequestParams( false ) ) == 0 )
					 || ( in_array( 'logged_in', $aEx ) && $bLoggedIn )
					 || ( in_array( 'ajax', $aEx ) && Services::WpGeneral()->isAjax() )
					 || ( in_array( 'cron', $aEx ) && Services::WpGeneral()->isCron() );

		if ( !$bExcluded && !$bLoggedIn ) {
			$bExcluded = ( in_array( 'search', $aEx ) && $this->isServiceIp_Search() )
						 || ( in_array( 'uptime', $aEx ) && $this->isServiceIp_Uptime() );
		}

		return $bExcluded;
	}

	/**
	 * @return bool
	 */
	private function isCustomExcluded() {
		/** @var Traffic\Options $oOpts */
		$oOpts = $this->getOptions();
		$oReq = Services::Request();

		$sAgent = $oReq->getUserAgent();
		$sPath = $oReq->getPath().( empty( $_GET ) ? '' : '?'.http_build_query( $_GET ) );

		$bExcluded = false;
		foreach ( $oOpts->getCustomExclusions() as $sExcl ) {
			if ( stripos( $sAgent, $sExcl ) !== false || stripos( $sPath, $sExcl ) !== false ) {
				$bExcluded = true;
			}
		}
		return $bExcluded;
	}

	/**
	 * @return bool
	 */
	private function isServiceIp_Search() {
		$oSP = Services::ServiceProviders();
		$sIp = Services::IP()->getRequestIp();
		$sAgent = (string)Services::Request()->getUserAgent();

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
	private function isServiceIp_Uptime() {
		$oSP = Services::ServiceProviders();
		$sIp = Services::IP()->getRequestIp();
		$sAgent = (string)Services::Request()->getUserAgent();

		return $oSP->isIp_Statuscake( $sIp, $sAgent )
			   || $oSP->isIp_UptimeRobot( $sIp, $sAgent )
			   || $oSP->isIp_Pingdom( $sIp, $sAgent )
			   || $oSP->isIpInCollection( $sIp, $oSP->getIps_NodePing() );
	}

	private function logTraffic() {
		/** @var \ICWP_WPSF_FeatureHandler_Traffic $oMod */
		$oMod = $this->getMod();
		$oReq = Services::Request();
		$oDbh = $oMod->getDbHandler_Traffic();

		// For multisites that are separated by sub-domains we also show the host.
		$sLeadingPath = Services::WpGeneral()->isMultisite_SubdomainInstall() ? $oReq->getHost() : '';

		/** @var EntryVO $oEntry */
		$oEntry = $oDbh->getVo();

		$oEntry->rid = $this->getCon()->getShortRequestId();
		$oEntry->uid = Services::WpUsers()->getCurrentWpUserId();
		$oEntry->ip = inet_pton( Services::IP()->getRequestIp() );
		$oEntry->verb = $oReq->getMethod();
		$oEntry->path = $sLeadingPath.$oReq->getPath().( empty( $_GET ) ? '' : '?'.http_build_query( $_GET ) );
		$oEntry->code = http_response_code();
		$oEntry->ua = $oReq->getUserAgent();
		$oEntry->trans = $oMod->getIfIpTransgressed() ? 1 : 0;

		$oDbh->getQueryInserter()
			 ->insert( $oEntry );
	}
}