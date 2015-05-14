<?php
/**
 * Copyright (c) 2015 iControlWP <support@icontrolwp.com>
 * All rights reserved.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
 * ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 */

if ( !class_exists( 'ICWP_WPSF_DataProcessor_V4', false ) ):

	class ICWP_WPSF_DataProcessor_V4 {

		/**
		 * @var ICWP_WPSF_DataProcessor_V4
		 */
		protected static $oInstance = NULL;

		/**
		 * @var bool
		 */
		public static $bUseFilterInput = false;

		/**
		 * @var string
		 */
		protected static $sIpAddress = false;

		/**
		 * @var string
		 */
		protected static $nIpAddressVersion = false;

		/**
		 * @var integer
		 */
		protected static $nRequestTime;

		/**
		 * @var array
		 */
		protected $aRequestUriParts;

		/**
		 * @return int
		 */
		public static function GetRequestTime() {
			if ( empty( self::$nRequestTime ) ) {
				self::$nRequestTime = time();
			}
			return self::$nRequestTime;
		}

		/**
		 *
		 * @param boolean $bAsHuman
		 * @return bool|integer - visitor IP Address as IP2Long
		 */
		public function getVisitorIpAddress( $bAsHuman = true ) {

			if ( empty( self::$sIpAddress ) ) {
				self::$sIpAddress = $this->findViableVisitorIp();
			}

			if ( !self::$sIpAddress || $bAsHuman ) {
				return self::$sIpAddress;
			}

			// If it's IPv6 we never return as long (we can't!)
			return ( $this->getVisitorIpVersion() == 4 ) ? ip2long( self::$sIpAddress ) : self::$sIpAddress;
		}

		/**
		 * Cloudflare compatible.
		 *
		 * @return string|bool
		 */
		protected function findViableVisitorIp() {

			$aAddressSourceOptions = array(
				'HTTP_CF_CONNECTING_IP',
				'HTTP_CLIENT_IP',
				'HTTP_X_FORWARDED_FOR',
				'HTTP_X_FORWARDED',
				'HTTP_FORWARDED',
				'REMOTE_ADDR'
			);

			$sIpToReturn = false;
			foreach( $aAddressSourceOptions as $sOption ) {

				$sIpAddressToTest = self::FetchServer( $sOption );
				if ( empty( $sIpAddressToTest ) ) {
					continue;
				}

				$aIpAddresses = explode( ',', $sIpAddressToTest ); //sometimes a comma-separated list is returned
				foreach( $aIpAddresses as $sIpAddress ) {
					if ( empty( $sIpAddress ) ) {
						continue;
					}

					// this version checking serves to weed out IPv6 if filter_var isn't supported by their PHP.
					// I.e. We ONLY support IPv6 if filter_var() is supported.
					$nVersion = $this->getIpAddressVersion( $sIpAddress );
					if ( $nVersion != false ) {
						$sIpToReturn = $sIpAddress;
						break(2);
					}
				}
			}
			return $sIpToReturn;
		}

		/**
		 * @return string URI Path in lowercase
		 */
		public function getRequestPath() {
			$aParts = $this->getRequestUriParts();
			return isset( $aParts[ 'path' ] ) ? strtolower( $aParts[ 'path' ] ) : '';
		}

		/**
		 * @return string
		 */
		public function getRequestUri() {
			return $this->FetchServer( 'REQUEST_URI' );
		}

		/**
		 * @return array|false
		 */
		public function getRequestUriParts() {
			if ( !isset( $this->aRequestUriParts ) ) {
				$aParts = @parse_url( $this->getRequestUri() );
				if ( empty( $aParts ) ) { //we failed so we'll try manually.
					$aParts = array();
					$aExploded = explode( '?', $this->getRequestUri() );
					if ( !empty( $aExploded[0] ) ) {
						$aParts['path'] = $aExploded[0];
					}
					if ( !empty( $aExploded[1] ) ) {
						$aParts['query'] = $aExploded[1];
					}
				}
				$this->aRequestUriParts = $aParts;
			}
			return $this->aRequestUriParts;
		}

		/**
		 * @param bool $bIncludeCookie
		 * @return array
		 */
		public function getRawRequestParams( $bIncludeCookie = true ) {
			$aParams = array_merge( $_GET, $_POST );
			return $bIncludeCookie ? array_merge( $aParams, $_COOKIE ) : $aParams;
		}

		/**
		 * @param string $sPath
		 * @return string
		 */
		public function getExtension( $sPath ) {
			$nLastPeriod = strrpos( $sPath, '.' );
			return ( $nLastPeriod === false ) ? $sPath : str_replace( '.', '', substr( $sPath, $nLastPeriod ) );
		}

		/**
		 * @return bool|int|string
		 */
		public function getVisitorIpVersion() {
			if ( empty( self::$nIpAddressVersion ) ) {
				self::$nIpAddressVersion = $this->getIpAddressVersion( $this->getVisitorIpAddress( true ) );
			}
			return self::$nIpAddressVersion;
		}

		/**
		 * Assumes a valid IPv4 address is provided as we're only testing for a whether the IP is public or not.
		 *
		 * @param string $sIpAddress
		 * @uses filter_var
		 * @return boolean
		 */
		public static function IsAddressInPublicIpRange( $sIpAddress ) {
			return function_exists('filter_var') && filter_var( $sIpAddress, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE );
		}

		/**
		 * @param string $sAddresses
		 *
		 * @return array
		 */
		public function extractIpAddresses( $sAddresses = '' ) {

			$aRawAddresses = array();
			if ( empty( $sAddresses ) ) {
				return $aRawAddresses;
			}

			$aRawList = array_map( 'trim', explode( "\n", $sAddresses ) );

			foreach( $aRawList as $sKey => $sRawAddressLine ) {

				if ( empty( $sRawAddressLine ) ) {
					continue;
				}

				// Each line can have a Label which is the IP separated with a space.
				$aParts = explode( ' ', $sRawAddressLine, 2 );
				if ( count( $aParts ) == 1 ) {
					$aParts[] = '';
				}
				$aRawAddresses[ $aParts[0] ] = trim( $aParts[1] );
			}
			return $this->addNewRawIps( array(), $aRawAddresses );
		}

		/**
		 * @param string $sRawList
		 * @return array
		 */
		public function extractCommaSeparatedList( $sRawList = '' ) {

			$aRawList = array();
			if ( empty( $sRawList ) ) {
				return $aRawList;
			}

			$aRawList = array_map( 'trim', preg_split( '/\r\n|\r|\n/', $sRawList ) );
			$aNewList = array();
			$bHadStar = false;
			foreach( $aRawList as $sKey => $sRawLine ) {

				if ( empty( $sRawLine ) ) {
					continue;
				}
				$sRawLine = str_replace( ' ', '', $sRawLine );
				$aParts = explode( ',', $sRawLine, 2 );
				// we only permit 1x line beginning with *
				if ( $aParts[0] == '*' ) {
					if ( $bHadStar ) {
						continue;
					}
					$bHadStar = true;
				}
				else {
					//If there's only 1 item on the line, we assume it to be a global
					// parameter rule
					if ( count( $aParts ) == 1 || empty( $aParts[1] ) ) { // there was no comma in this line in the first place
						array_unshift( $aParts, '*' );
					}
				}

				$aParams = empty( $aParts[1] )? array() : explode( ',', $aParts[1] );
				$aNewList[ $aParts[0] ] = $aParams;
			}
			return $aNewList;
		}

		/**
		 * Given a list of new IPv4 address ($inaNewRawAddresses) it'll add them to the existing list
		 * ($inaCurrent) where they're not already found
		 *
		 * @param array $aCurrent			- the list to which to add the new addresses
		 * @param array $aNewRawAddresses	- the new IPv4 addresses
		 * @param int $outnNewAddedCount	- the count of newly added IPs
		 * @return array
		 */
		public function addNewRawIps( $aCurrent, $aNewRawAddresses, &$outnNewAddedCount = 0 ) {

			$outnNewAddedCount = 0;

			if ( empty( $aNewRawAddresses ) ) {
				return $aCurrent;
			}

			if ( !array_key_exists( 'ips', $aCurrent ) ) {
				$aCurrent['ips'] = array();
			}
			if ( !array_key_exists( 'meta', $aCurrent ) ) {
				$aCurrent['meta'] = array();
			}

			foreach( $aNewRawAddresses as $sRawIpAddress => $sLabel ) {
				$mVerifiedIp = $this->verifyIp( $sRawIpAddress );
				if ( $mVerifiedIp !== false && !in_array( $mVerifiedIp, $aCurrent['ips'] ) ) {
					$aCurrent['ips'][] = $mVerifiedIp;
					if ( empty($sLabel) ) {
						$sLabel = 'no label';
					}
					$aCurrent['meta'][ md5( $mVerifiedIp ) ] = $sLabel;
					$outnNewAddedCount++;
				}
			}
			return $aCurrent;
		}

		/**
		 * @param array $aCurrent
		 * @param array $aRawAddresses - should be a plain numerical array of IPv4 addresses
		 * @return array:
		 */
		public function removeRawIps( $aCurrent, $aRawAddresses ) {
			if ( empty( $aRawAddresses ) ) {
				return $aCurrent;
			}

			if ( !array_key_exists( 'ips', $aCurrent ) ) {
				$aCurrent['ips'] = array();
			}
			if ( !array_key_exists( 'meta', $aCurrent ) ) {
				$aCurrent['meta'] = array();
			}

			foreach( $aRawAddresses as $sRawIpAddress ) {
				$mVerifiedIp = $this->verifyIp( $sRawIpAddress );
				if ( $mVerifiedIp === false ) {
					continue;
				}
				$mKey = array_search( $mVerifiedIp, $aCurrent['ips'] );
				if ( $mKey !== false ) {
					unset( $aCurrent['ips'][$mKey] );
					unset( $aCurrent['meta'][ md5( $mVerifiedIp ) ] );
				}
			}
			return $aCurrent;
		}

		/**
		 * @param string $sIpAddress
		 * @return bool|int|string
		 */
		public function verifyIp( $sIpAddress ) {

			$sAddress = self::Clean_Ip( $sIpAddress );

			// Now, determine if this is an IP range, or just a plain IP address.
			if ( strpos( $sAddress, '-' ) === false ) { // not an IP range

				$nVersion = $this->getIpAddressVersion( $sIpAddress );
				if ( $nVersion == 4 ) {
					return ip2long( $sIpAddress );
				}
				else if ( $nVersion == 6 ) {
					return $sIpAddress;
				}
				return false;

			}
			else {
				return $this->verifyIpRange( $sAddress );
			}
		}

		/**
		 * @param string $sRawAddress
		 *
		 * @return string
		 */
		public static function Clean_Ip( $sRawAddress ) {
			$sRawAddress = preg_replace( '/[a-z\s]/i', '', $sRawAddress );
			$sRawAddress = str_replace( '.', 'PERIOD', $sRawAddress );
			$sRawAddress = str_replace( '-', 'HYPEN', $sRawAddress );
			$sRawAddress = str_replace( ':', 'COLON', $sRawAddress );
			$sRawAddress = preg_replace( '/[^a-z0-9]/i', '', $sRawAddress );
			$sRawAddress = str_replace( 'PERIOD', '.', $sRawAddress );
			$sRawAddress = str_replace( 'HYPEN', '-', $sRawAddress );
			$sRawAddress = str_replace( 'COLON', ':', $sRawAddress );
			return $sRawAddress;
		}

		/**
		 * Taken from http://www.phacks.net/detecting-search-engine-bot-and-web-spiders/
		 */
		public static function IsSearchEngineBot() {

			$sUserAgent = self::FetchServer( 'HTTP_USER_AGENT' );
			if ( empty( $sUserAgent ) ) {
				return false;
			}

			$sBots = 'Googlebot|bingbot|Twitterbot|Baiduspider|ia_archiver|R6_FeedFetcher|NetcraftSurveyAgent'
				.'|Sogou web spider|Yahoo! Slurp|facebookexternalhit|PrintfulBot|msnbot|UnwindFetchor|urlresolver|Butterfly|TweetmemeBot';

			return ( preg_match( "/$sBots/", $sUserAgent ) > 0 );
		}

		/**
		 * The only ranges currently accepted are a.b.c.d-f.g.h.j
		 *
		 * @param string $sIpAddressRange
		 * @return string|boolean
		 */
		public function verifyIpRange( $sIpAddressRange ) {

			list( $sIpRangeStart, $sIpRangeEnd ) = explode( '-', $sIpAddressRange, 2 );

			$nStartVersion = $this->getIpAddressVersion( $sIpRangeStart );
			$nEndVersion = $this->getIpAddressVersion( $sIpRangeEnd );

			if ( !$nStartVersion || !$nEndVersion || ( $nStartVersion != $nEndVersion ) ) {
				return false;
			}

			if ( $nStartVersion == 4 ) {
				$sIpRangeStart = ip2long( $sIpRangeStart );
				$sIpRangeEnd = ip2long( $sIpRangeEnd );

				// reorder if possible
				if ( ( $sIpRangeStart > 0 && $sIpRangeEnd > 0 && $sIpRangeStart > $sIpRangeEnd )
				     || ( $sIpRangeStart < 0 && $sIpRangeEnd < 0 && $sIpRangeStart > $sIpRangeEnd ) ) {
					$nTemp = $sIpRangeStart;
					$sIpRangeStart = $sIpRangeEnd;
					$sIpRangeEnd = $nTemp;
				}
			}
			else {
				// IPv6 RANGES are not supported (yet)
				return false;
			}

			if ( $sIpRangeStart == $sIpRangeEnd ) {
				return $sIpRangeStart;
			}
			else {
				return $sIpRangeStart.'-'.$sIpRangeEnd;
			}
		}

		/**
		 * @param $sRawKeys
		 * @return array
		 */
		public static function CleanYubikeyUniqueKeys( $sRawKeys ) {
			$aKeys = explode( "\n", $sRawKeys );
			foreach( $aKeys as $nIndex => $sUsernameKey ) {
				if ( empty( $sUsernameKey ) ) {
					unset( $aKeys[$nIndex] );
					continue;
				}
				$aParts = array_map( 'trim', explode( ',', $sUsernameKey ) );
				if ( empty( $aParts[0] ) || empty( $aParts[1] ) || strlen( $aParts[1] ) < 12 ) {
					unset( $aKeys[$nIndex] );
					continue;
				}
				$aParts[1] = substr( $aParts[1], 0, 12 );
				$aKeys[$nIndex] = array( $aParts[0] => $aParts[1] );
			}
			return $aKeys;
		}

		/**
		 * Strength can be 1, 3, 7, 15
		 *
		 * @param integer $nLength
		 * @param integer $nStrength
		 * @param boolean $bIgnoreAmb
		 *
		 * @return string
		 */
		static public function GenerateRandomString( $nLength = 10, $nStrength = 7, $bIgnoreAmb = true ) {
			$aChars = array( 'abcdefghijkmnopqrstuvwxyz' );

			if ( $nStrength & 2 ) {
				$aChars[] = '023456789';
			}

			if ( $nStrength & 4 ) {
				$aChars[] = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
			}

			if ( $nStrength & 8 ) {
				$aChars[] = '$%^&*#';
			}

			if ( !$bIgnoreAmb ) {
				$aChars[] = 'OOlI1';
			}

			$sPassword = '';
			$sCharset = implode( '', $aChars );
			for ( $i = 0; $i < $nLength; $i++ ) {
				$sPassword .= $sCharset[(rand() % strlen( $sCharset ))];
			}
			return $sPassword;
		}

		/**
		 * @return string
		 */
		static public function GenerateRandomLetter() {
			$sAtoZ = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
			$nRandomInt = rand( 0, (strlen( $sAtoZ ) - 1) );
			return $sAtoZ[ $nRandomInt ];
		}

		/**
		 * @return bool
		 */
		static public function GetIsRequestPost() {
			return ( self::GetRequestMethod() == 'post' );
		}

		/**
		 * Returns the current request method as an all-lower-case string
		 *
		 * @return bool|string
		 */
		static public function GetRequestMethod() {
			$sRequestMethod = self::FetchServer( 'REQUEST_METHOD' );
			return ( empty( $sRequestMethod ) ? false : strtolower( $sRequestMethod ) );
		}

		/**
		 * @return string|null
		 */
		static public function GetScriptName() {
			$sScriptName = self::FetchServer( 'SCRIPT_NAME' );
			return !empty( $sScriptName )? $sScriptName : self::FetchServer( 'PHP_SELF' );
		}

		/**
		 * @return bool
		 */
		static public function GetUseFilterInput() {
			return self::$bUseFilterInput && function_exists( 'filter_input' );
		}

		/**
		 * @param array $aArray
		 * @param string $sKey		The array key to fetch
		 * @param mixed $mDefault
		 * @return mixed|null
		 */
		public static function ArrayFetch( &$aArray, $sKey, $mDefault = null ) {
			if ( empty( $aArray ) || !isset( $aArray[$sKey] ) ) {
				return $mDefault;
			}
			return $aArray[$sKey];
		}

		/**
		 * @param string $sKey		The $_COOKIE key
		 * @param mixed $mDefault
		 * @return mixed|null
		 */
		public static function FetchCookie( $sKey, $mDefault = null ) {
			if ( self::GetUseFilterInput() && defined( 'INPUT_COOKIE' ) ) {
				$mPossible = filter_input( INPUT_COOKIE, $sKey );
				if ( !empty( $mPossible ) ) {
					return $mPossible;
				}
			}
			return self::ArrayFetch( $_COOKIE, $sKey, $mDefault );
		}

		/**
		 * @param string $sKey
		 * @param mixed $mDefault
		 * @return mixed|null
		 */
		public static function FetchEnv( $sKey, $mDefault = null ) {
			if ( self::GetUseFilterInput() && defined( 'INPUT_ENV' ) ) {
				$sPossible = filter_input( INPUT_ENV, $sKey );
				if ( !empty( $sPossible ) ) {
					return $sPossible;
				}
			}
			return self::ArrayFetch( $_ENV, $sKey, $mDefault );
		}

		/**
		 * @param string $sKey
		 * @param mixed $mDefault
		 * @return mixed|null
		 */
		public static function FetchGet( $sKey, $mDefault = null ) {
			if ( self::GetUseFilterInput() && defined( 'INPUT_GET' ) ) {
				$mPossible = filter_input( INPUT_GET, $sKey );
				if ( !empty( $mPossible ) ) {
					return $mPossible;
				}
			}
			return self::ArrayFetch( $_GET, $sKey, $mDefault );
		}
		/**
		 * @param string $sKey		The $_POST key
		 * @param mixed $mDefault
		 * @return mixed|null
		 */
		public static function FetchPost( $sKey, $mDefault = null ) {
			if ( self::GetUseFilterInput() && defined( 'INPUT_POST' ) ) {
				$mPossible = filter_input( INPUT_POST, $sKey );
				if ( !empty( $mPossible ) ) {
					return $mPossible;
				}
			}
			return self::ArrayFetch( $_POST, $sKey, $mDefault );
		}

		/**
		 * @param string $sKey
		 * @param boolean $bIncludeCookie
		 * @param mixed $mDefault
		 *
		 * @return mixed|null
		 */
		public static function FetchRequest( $sKey, $bIncludeCookie = false, $mDefault = null ) {
			$mFetchVal = self::FetchPost( $sKey );
			if ( is_null( $mFetchVal ) ) {
				$mFetchVal = self::FetchGet( $sKey );
				if ( is_null( $mFetchVal && $bIncludeCookie ) ) {
					$mFetchVal = self::FetchCookie( $sKey );
				}
			}
			return is_null( $mFetchVal )? $mDefault : $mFetchVal;
		}

		/**
		 * @param string $sKey
		 * @param mixed $mDefault
		 * @return mixed|null
		 */
		public static function FetchServer( $sKey, $mDefault = null ) {
			if ( self::GetUseFilterInput() && defined( 'INPUT_SERVER' ) ) {
				$sPossible = filter_input( INPUT_SERVER, $sKey );
				if ( !empty( $sPossible ) ) {
					return $sPossible;
				}
			}
			return self::ArrayFetch( $_SERVER, $sKey, $mDefault );
		}

		/**
		 * @param $sData
		 *
		 * @return array|mixed
		 */
		public function doJsonDecode( $sData ) {
			if ( function_exists( 'json_decode' ) ) {
				return json_decode( $sData );
			}
			if ( !class_exists( 'JSON' )  ) {
				require_once( dirname(__FILE__).ICWP_DS.'json/JSON.php' );
			}
			$oJson = new JSON();
			return @$oJson->unserialize( $sData );
		}

		/**
		 * @param string $sRequestedUrl
		 * @param string $sBaseUrl
		 */
		public function doSendApache404( $sRequestedUrl, $sBaseUrl ) {
			header( 'HTTP/1.1 404 Not Found' );
			die( '<html><head><title>404 Not Found</title><style type="text/css"></style></head><body><h1>Not Found</h1><p>The requested URL '.$sRequestedUrl.' was not found on this server.</p><p>Additionally, a 404 Not Found error was encountered while trying to use an ErrorDocument to handle the request.</p><hr><address>Apache Server at '.$sBaseUrl.' Port 80</address></body></html>' );
		}

		/**
		 * @param $sKey
		 * @param $mValue
		 * @param int $nExpireLength
		 * @param null $sPath
		 * @param null $sDomain
		 * @param bool $bSsl
		 *
		 * @return bool
		 */
		public function setCookie( $sKey, $mValue, $nExpireLength = 3600, $sPath = null, $sDomain = null, $bSsl = false ) {
			return setcookie(
				$sKey,
				$mValue,
				$this->time() + $nExpireLength,
				( is_null( $sPath ) && defined( 'COOKIEPATH' ) ) ? COOKIEPATH : $sPath,
				( is_null( $sDomain ) && defined( 'COOKIE_DOMAIN' ) ) ? COOKIE_DOMAIN : $sDomain,
				$bSsl
			);
		}

		/**
		 * @param string $sKey
		 *
		 * @return bool
		 */
		public function setDeleteCookie( $sKey ) {
			unset( $_COOKIE[ $sKey ] );
			return $this->setCookie( $sKey, '', -3600 );
		}

		/**
		 * Effectively validates and IP Address.
		 *
		 * With IPv6, we only support this if filter_var() is supported.
		 *
		 * @param string $sIpAddress
		 *
		 * @return bool|int
		 */
		public function getIpAddressVersion( $sIpAddress ) {

			if ( function_exists( 'filter_var' ) ) {

				if ( defined( 'FILTER_FLAG_IPV4' ) && filter_var( $sIpAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
					return 4;
				}

				if ( defined( 'FILTER_FLAG_IPV6' ) && filter_var( $sIpAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
					return 6;
				}
			}

			if ( preg_match( '/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\z/', $sIpAddress ) ) { //It's a valid IPv4 format, now check components
				$aParts = explode( '.', $sIpAddress );
				foreach ( $aParts as $sPart ) {
					$sPart = intval( $sPart );
					if ( $sPart < 0 || $sPart > 255 ) {
						return false;
					}
				}
				return 4;
			}

			return false;
		}

		/**
		 * @return string
		 */
		public function getPhpVersion() {
			return ( defined( 'PHP_VERSION' ) ? PHP_VERSION : phpversion() );
		}

		/**
		 * @return bool
		 */
		public function getCanOpensslSign() {
			return function_exists( 'base64_decode' )
				   && function_exists( 'openssl_sign' )
				   && function_exists( 'openssl_verify' )
				   && defined( 'OPENSSL_ALGO_SHA1' );
		}

		/**
		 * @param array $aArray
		 *
		 * @return stdClass
		 */
		public function convertArrayToStdClass( $aArray ) {
			$oObject = new stdClass();
			if ( !empty( $aArray ) && is_array( $aArray ) ) {
				foreach( $aArray as $sKey => $mValue ) {
					$oObject->{$sKey} = $mValue;
				}
			}
			return $oObject;
		}

		/**
		 * @return int
		 */
		public function time() {
			return self::GetRequestTime();
		}
	}
endif;

if ( !class_exists('ICWP_WPSF_DataProcessor') ):

	class ICWP_WPSF_DataProcessor extends ICWP_WPSF_DataProcessor_V4 {
		/**
		 * @return ICWP_WPSF_DataProcessor
		 */
		public static function GetInstance() {
			if ( is_null( self::$oInstance ) ) {
				self::$oInstance = new self();
			}
			return self::$oInstance;
		}
	}
endif;