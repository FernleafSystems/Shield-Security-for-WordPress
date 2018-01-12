<?php
if ( class_exists( 'ICWP_WPSF_Ip', false ) ) {
	return;
}

/**
 * This is taken straight out of https://github.com/symfony/HttpFoundation/blob/master/IpUtils.php
 */
class ICWP_WPSF_Ip extends ICWP_WPSF_Foundation {

	const IpifyEndpoint = 'https://api.ipify.org';

	/**
	 * @var string
	 */
	private $sIp;

	/**
	 * @var string
	 */
	private $sMyIp;

	/**
	 * @var ICWP_WPSF_Ip
	 */
	protected static $oInstance = null;

	/**
	 * @return ICWP_WPSF_Ip
	 */
	public static function GetInstance() {
		if ( is_null( self::$oInstance ) ) {
			self::$oInstance = new self();
		}
		return self::$oInstance;
	}

	/**
	 * Checks if an IPv4 or IPv6 address is contained in the list of given IPs or subnets.
	 * @param string       $requestIp IP to check
	 * @param string|array $ips       List of IPs or subnets (can be a string if only a single one)
	 * @return bool Whether the IP is valid
	 * @throws Exception When IPV6 support is not enabled
	 */
	public static function checkIp( $requestIp, $ips ) {
		if ( !is_array( $ips ) ) {
			$ips = array( $ips );
		}
		$method = substr_count( $requestIp, ':' ) > 1 ? 'checkIp6' : 'checkIp4';
		foreach ( $ips as $ip ) {
			if ( self::$method( $requestIp, $ip ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Compares two IPv4 addresses.
	 * In case a subnet is given, it checks if it contains the request IP.
	 * @param string $requestIp IPv4 address to check
	 * @param string $ip        IPv4 address or subnet in CIDR notation
	 * @return bool Whether the IP is valid
	 */
	public static function checkIp4( $requestIp, $ip ) {
		if ( false !== strpos( $ip, '/' ) ) {
			if ( '0.0.0.0/0' === $ip ) {
				return true;
			}
			list( $address, $netmask ) = explode( '/', $ip, 2 );
			if ( $netmask < 1 || $netmask > 32 ) {
				return false;
			}
		}
		else {
			$address = $ip;
			$netmask = 32;
		}
		return 0 === substr_compare( sprintf( '%032b', ip2long( $requestIp ) ), sprintf( '%032b', ip2long( $address ) ), 0, $netmask );
	}

	/**
	 * Compares two IPv6 addresses.
	 * In case a subnet is given, it checks if it contains the request IP.
	 * @author David Soria Parra <dsp at php dot net>
	 * @see    https://github.com/dsp/v6tools
	 * @param string $requestIp IPv6 address to check
	 * @param string $ip        IPv6 address or subnet in CIDR notation
	 * @return bool Whether the IP is valid
	 * @throws Exception When IPV6 support is not enabled
	 */
	public static function checkIp6( $requestIp, $ip ) {
		if ( !( ( extension_loaded( 'sockets' ) && defined( 'AF_INET6' ) ) || @inet_pton( '::1' ) ) ) {
			throw new Exception( 'Unable to check Ipv6. Check that PHP was not compiled with option "disable-ipv6".' );
		}
		if ( false !== strpos( $ip, '/' ) ) {
			list( $address, $netmask ) = explode( '/', $ip, 2 );
			if ( $netmask < 1 || $netmask > 128 ) {
				return false;
			}
		}
		else {
			$address = $ip;
			$netmask = 128;
		}
		$bytesAddr = unpack( 'n*', inet_pton( $address ) );
		$bytesTest = unpack( 'n*', inet_pton( $requestIp ) );
		for ( $i = 1, $ceil = ceil( $netmask/16 ) ; $i <= $ceil ; ++$i ) {
			$left = $netmask - 16*( $i - 1 );
			$left = ( $left <= 16 ) ? $left : 16;
			$mask = ~( 0xffff >> $left ) & 0xffff;
			if ( ( $bytesAddr[ $i ] & $mask ) != ( $bytesTest[ $i ] & $mask ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * @param string $sIp
	 * @return bool|int
	 */
	public function getIpVersion( $sIp ) {
		if ( filter_var( $sIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			return 4;
		}
		if ( filter_var( $sIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			return 6;
		}
		return false;
	}

	/**
	 * @param boolean $bAsHuman
	 * @return int|string|bool - visitor IP Address as IP2Long
	 */
	public function getRequestIp( $bAsHuman = true ) {

		if ( empty( $this->sIp ) ) {
			$aResult = $this->findViableVisitorIp();
			$this->sIp = $aResult[ 'ip' ];
		}
		if ( !$this->sIp || $bAsHuman ) {
			return $this->sIp;
		}

		// If it's IPv6 we never return as long (we can't!)
		return ( $this->getIpVersion( $this->sIp ) == 4 ) ? ip2long( $this->sIp ) : $this->sIp;
	}

	/**
	 * @param string $sIp
	 * @return bool
	 */
	public function isCloudFlareIp( $sIp ) {
		if ( $this->getIpVersion( $sIp ) == 4 ) {
			return $this->checkIp( $sIp, $this->getCloudFlareIpsV4() );
		}
		else {
			return $this->checkIp( $sIp, $this->getCloudFlareIpsV6() );
		}
	}

	/**
	 * @param string $sIp
	 * @param bool   $flags
	 * @return boolean
	 */
	public function isValidIp( $sIp, $flags = null ) {
		return filter_var( $sIp, FILTER_VALIDATE_IP, $flags );
	}

	/**
	 * Assumes a valid IPv4 address is provided as we're only testing for a whether the IP is public or not.
	 * @param string $sIp
	 * @return boolean
	 */
	public function isValidIp_PublicRange( $sIp ) {
		return $this->isValidIp( $sIp, FILTER_FLAG_NO_PRIV_RANGE );
	}

	/**
	 * @param string $sIp
	 * @return boolean
	 */
	public function isValidIp_PublicRemote( $sIp ) {
		return $this->isValidIp( $sIp, ( FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) );
	}

	/**
	 * @param string $sIp
	 * @return boolean
	 */
	public function isValidIpRange( $sIp ) {
		if ( strpos( $sIp, '/' ) == false ) {
			return false;
		}
		$aParts = explode( '/', $sIp );
		return filter_var( $aParts[ 0 ], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) && ( 0 < $aParts[ 1 ] && $aParts[ 1 ] < 33 );
	}

	/**
	 * Checks:
	 * - valid public remote IP
	 * - Not CloudFlare
	 * - Not the IP of the currently running server if this is provided
	 * @param string $sIp
	 * @param string $sHostIp
	 * @return bool
	 */
	public function isViablePublicVisitorIp( $sIp, $sHostIp = '' ) {
		return !empty( $sIp ) && $this->isValidIp_PublicRemote( $sIp ) && !$this->isCloudFlareIp( $sIp )
			   && ( empty( $sHostIp ) || !$this->checkIp( $sIp, $sHostIp ) );
	}

	/**
	 * @param string $sIp
	 * @return $this
	 */
	public function setRequestIpAddress( $sIp ) {
		$this->sIp = $sIp;
		return $this;
	}

	/**
	 * @param string $sIp
	 * @return $this
	 */
	public function setServerIpAddress( $sIp ) {
		$this->sMyIp = $sIp;
		return $this;
	}

	/**
	 * @return string
	 */
	public function whatIsMyIp() {

		if ( empty( $this->sMyIp ) ) {
			$sIp = $this->loadFS()
						->getUrlContent( self::IpifyEndpoint );
			if ( is_string( $sIp ) ) {
				$sIp = trim( $sIp );
			}
			$this->sMyIp = empty( $sIp ) ? '' : $sIp;
		}
		return $this->sMyIp;
	}

	/**
	 * @param string $sVisitorIp
	 * @return string
	 */
	public function determineSourceFromIp( $sVisitorIp ) {
		$oDp = $this->loadDP();

		$sBestSource = null;
		foreach ( $this->getIpSourceOptions() as $sSource ) {

			$sIpToTest = $oDp->FetchServer( $sSource );
			if ( empty( $sIpToTest ) ) {
				continue;
			}

			// sometimes a comma-separated list is returned
			$aIpAddresses = array_map( 'trim', explode( ',', $sIpToTest ) );
			foreach ( $aIpAddresses as $sIp ) {

				if ( $sVisitorIp == $sIp ) {
					$sBestSource = $sSource;
					break( 2 );
				}
			}
		}

		return $sBestSource;
	}

	/**
	 * @return string|false
	 */
	public function discoverViableRequestIpSource() {
		$aResult = $this->findViableVisitorIp( true );
		return $aResult[ 'source' ];
	}

	/**
	 * Cloudflare compatible.
	 * @param bool $bRemoteVerify
	 * @return array
	 */
	protected function findViableVisitorIp( $bRemoteVerify = false ) {

		$sMyIp = $bRemoteVerify ? $this->whatIsMyIp() : null;

		$sIpToReturn = false;
		$sSource = false;
		$oDp = $this->loadDP();
		foreach ( $this->getIpSourceOptions() as $sSource ) {

			$sIpToTest = $oDp->FetchServer( $sSource );
			if ( empty( $sIpToTest ) ) {
				continue;
			}

			// sometimes a comma-separated list is returned
			$aIpAddresses = array_map( 'trim', explode( ',', $sIpToTest ) );
			foreach ( $aIpAddresses as $sIp ) {

				if ( $this->isViablePublicVisitorIp( $sIp ) ) {
					$sIpToReturn = $sIp;
					break( 2 );
				}
			}
		}

		return array(
			'source' => $sSource,
			'ip'     => $sIpToReturn
		);
	}

	/**
	 * @return string[]
	 */
	protected function getIpSourceOptions() {
		return array(
			'REMOTE_ADDR',
			'HTTP_CF_CONNECTING_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_X_REAL_IP',
			'HTTP_X_SUCURI_CLIENTIP',
			'HTTP_INCAP_CLIENT_IP',
			'HTTP_FORWARDED',
			'HTTP_CLIENT_IP'
		);
	}

	/**
	 * @return string[]
	 */
	protected function getCloudFlareIpsV4() {
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
	protected function getCloudFlareIpsV6() {
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
}