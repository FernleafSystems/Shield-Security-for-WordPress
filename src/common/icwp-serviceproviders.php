<?php
if ( class_exists( 'ICWP_WPSF_ServiceProviders', false ) ) {
	return;
}

/**
 * Class ICWP_WPSF_ServiceProviders
 */
class ICWP_WPSF_ServiceProviders extends ICWP_WPSF_Foundation {

	/**
	 * @var string
	 */
	protected $sPrefix = '';

	/**
	 * @var ICWP_WPSF_ServiceProviders
	 */
	protected static $oInstance = null;

	/**
	 * @return ICWP_WPSF_ServiceProviders
	 */
	public static function GetInstance() {
		if ( is_null( self::$oInstance ) ) {
			self::$oInstance = new self();
		}
		return self::$oInstance;
	}

	/**
	 * @return string[]
	 */
	public function getIps_CloudFlareV4() {
		return array(
			'103.21.244.0/22',
			'103.22.200.0/22',
			'103.31.4.0/22',
			'104.16.0.0/12',
			'108.162.192.0/18',
			'131.0.72.0/22',
			'141.101.64.0/18',
			'162.158.0.0/15',
			'172.64.0.0/13',
			'173.245.48.0/20',
			'188.114.96.0/20',
			'190.93.240.0/20',
			'197.234.240.0/22',
			'198.41.128.0/17'
		);
	}

	/**
	 * @return string[]
	 */
	public function getIps_CloudFlareV6() {
		return array(
			'2400:cb00::/32',
			'2405:8100::/32',
			'2405:b500::/32',
			'2606:4700::/32',
			'2803:f800::/32',
			'2c0f:f248::/32',
			'2a06:98c0::/29'
		);
	}

	/**
	 * @return string[]
	 */
	public function getIps_DuckDuckGo() {
		return array( '107.20.237.51', '23.21.226.191', '107.21.1.8', '54.208.102.37' );
	}

	/**
	 * @return array[]
	 */
	public function getIps_Pingdom() {
		$oWp = $this->loadWp();
		$aIps = $oWp->getTransient( $this->prefix( 'serviceips_pingdom' ) );
		if ( empty( $aIps ) ) {
			$aIps = array(
				4 => $this->downloadServiceIps_Pingdom( 4 ),
				6 => $this->downloadServiceIps_Pingdom( 6 )
			);
			$oWp->setTransient( $this->prefix( 'serviceips_pingdom' ), $aIps, WEEK_IN_SECONDS*4 );
		}
		return $aIps;
	}

	/**
	 * @return string[]
	 */
	public function getIps_Statuscake() {
		$oWp = $this->loadWp();
		$aIps = $oWp->getTransient( $this->prefix( 'serviceips_statuscake' ) );
		if ( empty( $aIps ) || !is_array( $aIps ) ) {
			$aIps = $this->downloadServiceIps_StatusCake();
			$oWp->setTransient( $this->prefix( 'serviceips_statuscake' ), $aIps, WEEK_IN_SECONDS*4 );
		}
		return $aIps;
	}

	/**
	 * @return array[]
	 */
	public function getIps_UptimeRobot() {
		$oWp = $this->loadWp();
		$aIps = $oWp->getTransient( $this->prefix( 'serviceips_uptimerobot' ) );
		if ( empty( $aIps ) ) {
			$aIps = array(
				4 => $this->downloadServiceIps_UptimeRobot( 4 ),
				6 => $this->downloadServiceIps_UptimeRobot( 6 )
			);
			$oWp->setTransient( $this->prefix( 'serviceips_uptimerobot' ), $aIps, WEEK_IN_SECONDS*4 );
		}
		return $aIps;
	}

	/**
	 * @param string $sIp
	 * @param string $sUserAgent
	 * @return bool
	 */
	public function isIp_AppleBot( $sIp, $sUserAgent ) {
		$oWp = $this->loadWp();

		$sStoreKey = $this->prefix( 'serviceips_applebot' );
		$aIps = $oWp->getTransient( $sStoreKey );
		if ( !is_array( $aIps ) ) {
			$aIps = array();
		}

		if ( !in_array( $sIp, $aIps ) && $this->verifyIp_AppleBot( $sIp, $sUserAgent ) ) {
			$aIps[] = $sIp;
			$aIps = $oWp->setTransient( $sStoreKey, $aIps, WEEK_IN_SECONDS*4 );
		}

		return in_array( $sIp, $aIps );
	}

	/**
	 * @param string $sIp
	 * @param string $sUserAgent
	 * @return bool
	 */
	public function isIp_BaiduBot( $sIp, $sUserAgent ) {
		$oWp = $this->loadWp();

		$sStoreKey = $this->prefix( 'serviceips_baidubot' );
		$aIps = $oWp->getTransient( $sStoreKey );
		if ( !is_array( $aIps ) ) {
			$aIps = array();
		}

		if ( !in_array( $sIp, $aIps ) && $this->verifyIp_BaiduBot( $sIp, $sUserAgent ) ) {
			$aIps[] = $sIp;
			$aIps = $oWp->setTransient( $sStoreKey, $aIps, WEEK_IN_SECONDS*4 );
		}

		return in_array( $sIp, $aIps );
	}

	/**
	 * @param string $sIp
	 * @param string $sUserAgent
	 * @return bool
	 */
	public function isIp_BingBot( $sIp, $sUserAgent ) {
		$oWp = $this->loadWp();

		$sStoreKey = $this->prefix( 'serviceips_bingbot' );
		$aIps = $oWp->getTransient( $sStoreKey );
		if ( !is_array( $aIps ) ) {
			$aIps = array();
		}

		if ( !in_array( $sIp, $aIps ) && $this->verifyIp_BingBot( $sIp, $sUserAgent ) ) {
			$aIps[] = $sIp;
			$aIps = $oWp->setTransient( $sStoreKey, $aIps, WEEK_IN_SECONDS*4 );
		}

		return in_array( $sIp, $aIps );
	}

	/**
	 * @param string $sIp
	 * @return bool
	 */
	public function isIp_Cloudflare( $sIp ) {
		$bIs = false;
		try {
			$oIp = $this->loadIpService();
			if ( $oIp->getIpVersion( $sIp ) == 4 ) {
				$bIs = $oIp->checkIp( $sIp, $this->getIps_CloudFlareV4() );
			}
			else {
				$bIs = $oIp->checkIp( $sIp, $this->getIps_CloudFlareV6() );
			}
		}
		catch ( Exception $oE ) {
		}
		return $bIs;
	}

	/**
	 * https://duckduckgo.com/duckduckbot
	 * @param string $sIp
	 * @param string $sUserAgent
	 * @return bool
	 */
	public function isIp_DuckDuckGoBot( $sIp, $sUserAgent ) {
		$bIsBot = false;
		// We check the useragent if available
		if ( is_null( $sUserAgent ) || stripos( $sUserAgent, 'DuckDuckBot' ) !== false ) {
			$bIsBot = in_array( $sIp, $this->getIps_DuckDuckGo() );
		}
		return $bIsBot;
	}

	/**
	 * https://support.google.com/webmasters/answer/80553?hl=en
	 * @param string $sIp
	 * @param string $sUserAgent
	 * @return bool
	 */
	public function isIp_GoogleBot( $sIp, $sUserAgent ) {
		$oWp = $this->loadWp();

		$sStoreKey = $this->prefix( 'serviceips_googlebot' );
		$aIps = $oWp->getTransient( $sStoreKey );
		if ( !is_array( $aIps ) ) {
			$aIps = array();
		}

		if ( !in_array( $sIp, $aIps ) && $this->verifyIp_GoogleBot( $sIp, $sUserAgent ) ) {
			$aIps[] = $sIp;
			$aIps = $oWp->setTransient( $sStoreKey, $aIps, WEEK_IN_SECONDS*4 );
		}

		return in_array( $sIp, $aIps );
	}

	/**
	 * @param string $sIp
	 * @param string $sAgent
	 * @return bool
	 */
	public function isIp_Statuscake( $sIp, $sAgent ) {
		$bIsIp = false;
		if ( stripos( $sAgent, 'StatusCake' ) !== false ) {
			$aIps = $this->getIps_Statuscake();
			$bIsIp = in_array( $sIp, $aIps );
		}
		return $bIsIp;
	}

	/**
	 * @param string $sIp
	 * @param string $sAgent
	 * @return bool
	 */
	public function isIp_Pingdom( $sIp, $sAgent ) {
		$bIsIp = false;
		if ( stripos( $sAgent, 'pingdom.com' ) !== false ) {
			$aIps = $this->getIps_Pingdom();
			$bIsIp = in_array( $sIp, $aIps[ $this->loadIpService()->getIpVersion( $sIp ) ] );
		}
		return $bIsIp;
	}

	/**
	 * @param string $sIp
	 * @param string $sAgent
	 * @return bool
	 */
	public function isIp_UptimeRobot( $sIp, $sAgent ) {
		$bIsIp = false;
		if ( stripos( $sAgent, 'UptimeRobot' ) !== false ) {
			$aIps = $this->getIps_UptimeRobot();
			$bIsIp = in_array( $sIp, $aIps[ $this->loadIpService()->getIpVersion( $sIp ) ] );
		}
		return $bIsIp;
	}

	/**
	 * https://yandex.com/support/webmaster/robot-workings/check-yandex-robots.html
	 * @param string $sIp
	 * @param string $sUserAgent
	 * @return bool
	 */
	public function isIp_YandexBot( $sIp, $sUserAgent ) {
		$oWp = $this->loadWp();

		$sStoreKey = $this->prefix( 'serviceips_yandexbot' );
		$aIps = $oWp->getTransient( $sStoreKey );
		if ( !is_array( $aIps ) ) {
			$aIps = array();
		}

		if ( !in_array( $sIp, $aIps ) && $this->verifyIp_YandexBot( $sIp, $sUserAgent ) ) {
			$aIps[] = $sIp;
			$aIps = $oWp->setTransient( $sStoreKey, $aIps, WEEK_IN_SECONDS*4 );
		}

		return in_array( $sIp, $aIps );
	}

	/**
	 * https://yandex.com/support/webmaster/robot-workings/check-yandex-robots.html
	 * @param string $sIp
	 * @param string $sUserAgent
	 * @return bool
	 */
	public function isIp_YahooBot( $sIp, $sUserAgent ) {
		$oWp = $this->loadWp();

		$sStoreKey = $this->prefix( 'serviceips_yahoobot' );
		$aIps = $oWp->getTransient( $sStoreKey );
		if ( !is_array( $aIps ) ) {
			$aIps = array();
		}

		if ( !in_array( $sIp, $aIps ) && $this->verifyIp_YahooBot( $sIp, $sUserAgent ) ) {
			$aIps[] = $sIp;
			$aIps = $oWp->setTransient( $sStoreKey, $aIps, WEEK_IN_SECONDS*4 );
		}

		return in_array( $sIp, $aIps );
	}

	/**
	 * https://support.apple.com/en-gb/HT204683
	 * https://discussions.apple.com/thread/7090135
	 * Apple IPs start with '17.'
	 * @param string $sIp
	 * @param string $sUserAgent
	 * @return bool
	 */
	private function verifyIp_AppleBot( $sIp, $sUserAgent = '' ) {
		return ( $this->loadIpService()->getIpVersion( $sIp ) != 4 || strpos( $sIp, '17.' ) === 0 )
			   && $this->isIpOfBot( 'Applebot/', '#.*\.applebot.apple.com\.?$#i', $sIp, $sUserAgent );
	}

	/**
	 * @param string $sIp
	 * @param string $sUserAgent
	 * @return bool
	 */
	private function verifyIp_BaiduBot( $sIp, $sUserAgent = '' ) {
		return $this->isIpOfBot( 'baidu', '#.*\.crawl\.baidu\.(com|jp)\.?$#i', $sIp, $sUserAgent );
	}

	/**
	 * @param string $sIp
	 * @param string $sUserAgent
	 * @return bool
	 */
	private function verifyIp_BingBot( $sIp, $sUserAgent = '' ) {
		return $this->isIpOfBot( 'bingbot', '#.*\.search\.msn\.com\.?$#i', $sIp, $sUserAgent );
	}

	/**
	 * @param string $sIp
	 * @param string $sUserAgent
	 * @return bool
	 */
	private function verifyIp_GoogleBot( $sIp, $sUserAgent = '' ) {
		return $this->isIpOfBot( 'Googlebot', '#.*\.google(bot)?\.com\.?$#i', $sIp, $sUserAgent );
	}

	/**
	 * @param string $sIp
	 * @param string $sUserAgent
	 * @return bool
	 */
	private function verifyIp_YandexBot( $sIp, $sUserAgent = '' ) {
		return $this->isIpOfBot( 'yandex.com/bots', '#.*\.yandex?\.(com|ru|net)\.?$#i', $sIp, $sUserAgent );
	}

	/**
	 * @param string $sIp
	 * @param string $sUserAgent
	 * @return bool
	 */
	private function verifyIp_YahooBot( $sIp, $sUserAgent = '' ) {
		return $this->isIpOfBot( 'yahoo!', '#.*\.crawl\.yahoo\.net\.?$#i', $sIp, $sUserAgent );
	}

	/**
	 * Will test useragent, then attempt to resolve to hostname and back again
	 * https://www.elephate.com/detect-verify-crawlers/
	 * @param string $sBotUserAgent
	 * @param string $sBotHostPattern
	 * @param string $sReqIp
	 * @param string $sReqUserAgent
	 * @return bool
	 */
	private function isIpOfBot( $sBotUserAgent, $sBotHostPattern, $sReqIp, $sReqUserAgent = '' ) {
		$bIsBot = false;

		// We check the useragent if available
		if ( is_null( $sReqUserAgent ) || stripos( $sReqUserAgent, $sBotUserAgent ) !== false ) {
			$sHost = @gethostbyaddr( $sReqIp ); // returns the ip on failure
			if ( !empty( $sHost ) && ( $sHost != $sReqIp )
				 && preg_match( $sBotHostPattern, $sHost ) && gethostbyname( $sHost ) === $sReqIp ) {
				$bIsBot = true;
			}
		}
		return $bIsBot;
	}

	/**
	 * @param int $sIpVersion
	 * @return string[]
	 */
	private function downloadServiceIps_Pingdom( $sIpVersion = 4 ) {
		$sUrl = sprintf( 'https://my.pingdom.com/probes/ipv%s', $sIpVersion );
		return array_filter( array_map( 'trim', explode( "\n", $this->loadFS()->getUrlContent( $sUrl ) ) ) );
	}

	/**
	 * @return string[]
	 */
	private function downloadServiceIps_StatusCake() {
		$aIps = array();
		$aData = @json_decode( $this->loadFS()
									->getUrlContent( 'https://app.statuscake.com/Workfloor/Locations.php?format=json' ), true );
		if ( is_array( $aData ) ) {
			foreach ( $aData as $aItem ) {
				$aIps[] = $aItem[ 'ip' ];
			}
		}
		return $aIps;
	}

	/**
	 * @param int $sIpVersion
	 * @return string[]
	 */
	private function downloadServiceIps_UptimeRobot( $sIpVersion = 4 ) {
		$sUrl = sprintf( 'https://uptimerobot.com/inc/files/ips/IPv%s.txt', $sIpVersion );
		return array_filter( array_map( 'trim', explode( "\n", $this->loadFS()->getUrlContent( $sUrl ) ) ) );
	}
}