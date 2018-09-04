<?php
if ( class_exists( 'ICWP_WPSF_DataProcessor', false ) ) {
	return;
}

class ICWP_WPSF_DataProcessor extends ICWP_WPSF_Foundation {

	/**
	 * @var ICWP_WPSF_DataProcessor
	 */
	protected static $oInstance = null;

	/**
	 * @var bool
	 */
	public static $bUseFilterInput = false;

	/**
	 * @var integer
	 */
	protected static $nRequestTime;

	/**
	 * @var array
	 */
	protected $aRequestUriParts;

	protected function __construct() {
	}

	/**
	 * @return ICWP_WPSF_DataProcessor
	 */
	public static function GetInstance() {
		if ( is_null( self::$oInstance ) ) {
			self::$oInstance = new self();
		}
		return self::$oInstance;
	}

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
	 * @param array $aArray1
	 * @param array $aArray2
	 * @return array
	 */
	public function mergeArraysRecursive( $aArray1, $aArray2 ) {
		foreach ( $aArray2 as $key => $Value ) {
			if ( array_key_exists( $key, $aArray1 ) && is_array( $Value ) ) {
				$aArray1[ $key ] = $this->mergeArraysRecursive( $aArray1[ $key ], $aArray2[ $key ] );
			}
			else {
				$aArray1[ $key ] = $Value;
			}
		}
		return $aArray1;
	}

	/**
	 * @param string $sKey
	 * @param null   $mDefault
	 * @param bool   $bTrim -automatically trim whitespace
	 * @return mixed|null
	 */
	public function cookie( $sKey, $mDefault = null, $bTrim = true ) {
		$mVal = $this->FetchCookie( $sKey, $mDefault );
		return ( $bTrim && is_scalar( $mVal ) ) ? trim( $mVal ) : $mVal;
	}

	/**
	 * @param string $sKey
	 * @param null   $mDefault
	 * @param bool   $bTrim -automatically trim whitespace
	 * @return mixed|null
	 */
	public function query( $sKey, $mDefault = null, $bTrim = true ) {
		$mVal = $this->FetchGet( $sKey, $mDefault );
		return ( $bTrim && is_scalar( $mVal ) ) ? trim( $mVal ) : $mVal;
	}

	/**
	 * @param string $sKey
	 * @param null   $mDefault
	 * @param bool   $bTrim -automatically trim whitespace
	 * @return mixed|null
	 */
	public function post( $sKey, $mDefault = null, $bTrim = true ) {
		$mVal = $this->FetchPost( $sKey, $mDefault );
		return ( $bTrim && is_scalar( $mVal ) ) ? trim( $mVal ) : $mVal;
	}

	/**
	 * @param string $sKey
	 * @param null   $mDefault
	 * @param bool   $bTrim -automatically trim whitespace
	 * @return mixed|null
	 */
	public function server( $sKey, $mDefault = null, $bTrim = true ) {
		$mVal = $this->FetchServer( $sKey, $mDefault );
		return ( $bTrim && is_scalar( $mVal ) ) ? trim( $mVal ) : $mVal;
	}

	/**
	 * @param string $sKey
	 * @param null   $mDefault
	 * @param bool   $bIncludeCookie
	 * @param bool   $bTrim -automatically trim whitespace
	 * @return mixed|null
	 */
	public function request( $sKey, $bIncludeCookie = false, $mDefault = null, $bTrim = true ) {
		$mVal = $this->post( $sKey, null, $bTrim );
		if ( is_null( $mVal ) ) {
			$mVal = $this->query( $sKey, null, $bTrim );
			if ( is_null( $mVal && $bIncludeCookie ) ) {
				$mVal = self::FetchCookie( $sKey );
			}
		}
		return is_null( $mVal ) ? $mDefault : ( $bTrim && is_scalar( $mVal ) ) ? trim( $mVal ) : $mVal;
	}

	/**
	 * @return string URI Path in lowercase
	 */
	public function getRequestPath() {
		$aRequestParts = $this->getRequestUriParts();
		return $aRequestParts[ 'path' ];
	}

	/**
	 * @return string
	 */
	public function getRequestUri() {
		return $this->FetchServer( 'REQUEST_URI' );
	}

	/**
	 * @return string
	 */
	public function getUserAgent() {
		return $this->FetchServer( 'HTTP_USER_AGENT' );
	}

	/**
	 * @param bool $bIncludeCookie
	 * @return array
	 */
	public function getRequestParams( $bIncludeCookie = true ) {
		$aParams = array_merge( $_GET, $_POST );
		return $bIncludeCookie ? array_merge( $aParams, $_COOKIE ) : $aParams;
	}

	/**
	 * @return array
	 */
	public function getRequestUriParts() {
		if ( !isset( $this->aRequestUriParts ) ) {
			$aExploded = explode( '?', $this->getRequestUri(), 2 );
			$this->aRequestUriParts = array(
				'path'  => empty( $aExploded[ 0 ] ) ? '' : $aExploded[ 0 ],
				'query' => empty( $aExploded[ 1 ] ) ? '' : $aExploded[ 1 ],
			);
		}
		return $this->aRequestUriParts;
	}

	/**
	 * @param string $sPath
	 * @param string $sExtensionToAdd
	 * @return string
	 */
	public function addExtensionToFilePath( $sPath, $sExtensionToAdd ) {

		if ( strpos( $sExtensionToAdd, '.' ) === false ) {
			$sExtensionToAdd = '.'.$sExtensionToAdd;
		}

		if ( !$this->getIfStringEndsIn( $sPath, $sExtensionToAdd ) ) {
			$sPath = $sPath.$sExtensionToAdd;
		}
		return $sPath;
	}

	/**
	 * @param string $sHaystack
	 * @param string $sNeedle
	 * @return bool
	 */
	public function getIfStringEndsIn( $sHaystack, $sNeedle ) {
		$nNeedleLength = strlen( $sNeedle );
		$sStringEndsIn = substr( $sHaystack, strlen( $sHaystack ) - $nNeedleLength, $nNeedleLength );
		return ( $sStringEndsIn == $sNeedle );
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
	 * @return bool
	 */
	public function isWindows() {
		return strtoupper( substr( PHP_OS, 0, 3 ) ) === 'WIN';
	}

	/**
	 * @param string $sUrl
	 * @return string
	 */
	public function urlStripQueryPart( $sUrl ) {
		return preg_replace( '#\s?\?.*$#', '', $sUrl );
	}

	/**
	 * @param string $sUrl
	 * @return string
	 */
	public function urlStripSchema( $sUrl ) {
		return preg_replace( '#^((http|https):)?\/\/#i', '', $sUrl );
	}

	/**
	 * Will strip everything from a URL except Scheme+Host and requires that Scheme+Host be present
	 * @return string|false
	 */
	public function validateSimpleHttpUrl( $sUrl ) {
		$sValidatedUrl = false;

		$sUrl = trim( $this->urlStripQueryPart( $sUrl ) );
		if ( filter_var( $sUrl, FILTER_VALIDATE_URL ) ) { // we have a scheme+host
			$aParts = parse_url( $sUrl );
			if ( in_array( $aParts[ 'scheme' ], array( 'http', 'https' ) ) ) {
				$sValidatedUrl = rtrim( $sUrl, '/' );
			}
		}

		return $sValidatedUrl;
	}

	/**
	 * @param string $sEmail
	 * @return boolean
	 */
	public function validEmail( $sEmail ) {
		return ( !empty( $sEmail ) && function_exists( 'is_email' ) && is_email( $sEmail ) );
	}

	/**
	 * @param string $sUrl
	 * @return bool
	 */
	public function validUrl( $sUrl ) {
		return filter_var( $sUrl, FILTER_VALIDATE_URL );
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
		foreach ( $aRawList as $sKey => $sRawLine ) {

			if ( empty( $sRawLine ) ) {
				continue;
			}
			$sRawLine = str_replace( ' ', '', $sRawLine );
			$aParts = explode( ',', $sRawLine, 2 );
			// we only permit 1x line beginning with *
			if ( $aParts[ 0 ] == '*' ) {
				if ( $bHadStar ) {
					continue;
				}
				$bHadStar = true;
			}
			else {
				//If there's only 1 item on the line, we assume it to be a global
				// parameter rule
				if ( count( $aParts ) == 1 || empty( $aParts[ 1 ] ) ) { // there was no comma in this line in the first place
					array_unshift( $aParts, '*' );
				}
			}

			$aParams = empty( $aParts[ 1 ] ) ? array() : explode( ',', $aParts[ 1 ] );
			$aNewList[ $aParts[ 0 ] ] = $aParams;
		}
		return $aNewList;
	}

	/**
	 * @return string
	 */
	public function getRequestMethod() {
		$sRequestMethod = self::FetchServer( 'REQUEST_METHOD' );
		return ( empty( $sRequestMethod ) ? 'get' : strtolower( $sRequestMethod ) );
	}

	/**
	 * @return bool
	 */
	public function isMethodPost() {
		return ( $this->getRequestMethod() == 'post' );
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
	 * Strength can be 1, 3, 7, 15
	 * @param integer $nLength
	 * @param integer $nStrength
	 * @param boolean $bIgnoreAmb
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
		for ( $i = 0 ; $i < $nLength ; $i++ ) {
			$sPassword .= $sCharset[ ( rand()%strlen( $sCharset ) ) ];
		}
		return $sPassword;
	}

	/**
	 * @return string
	 */
	static public function GenerateRandomLetter() {
		$sAtoZ = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
		$nRandomInt = rand( 0, ( strlen( $sAtoZ ) - 1 ) );
		return $sAtoZ[ $nRandomInt ];
	}

	/**
	 * @return string|null
	 */
	static public function GetScriptName() {
		$sScriptName = self::FetchServer( 'SCRIPT_NAME' );
		return !empty( $sScriptName ) ? $sScriptName : self::FetchServer( 'PHP_SELF' );
	}

	/**
	 * @return bool
	 */
	static public function GetUseFilterInput() {
		return self::$bUseFilterInput && function_exists( 'filter_input' );
	}

	/**
	 * @param array  $aArray
	 * @param string $sKey The array key to fetch
	 * @param mixed  $mDefault
	 * @return mixed|null
	 */
	public static function ArrayFetch( &$aArray, $sKey, $mDefault = null ) {
		if ( !isset( $aArray[ $sKey ] ) ) {
			return $mDefault;
		}
		return $aArray[ $sKey ];
	}

	/**
	 * @param string $sKey The $_COOKIE key
	 * @param mixed  $mDefault
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
	 * @param mixed  $mDefault
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
	 * @deprecated
	 * @param string $sKey
	 * @param mixed  $mDefault
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
	 * @param string $sKey The $_POST key
	 * @param mixed  $mDefault
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
	 * @param mixed  $mDefault
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
	 * @param string $sRequestedUriPath
	 * @param string $sHostName - you can also send a full and valid URL
	 */
	public function doSendApache404( $sRequestedUriPath = '', $sHostName = '' ) {
		if ( empty( $sRequestedUriPath ) ) {
			$sRequestedUriPath = $this->server( 'REQUEST_URI' );
		}

		if ( empty( $sHostName ) ) {
			$sHostName = $this->server( 'SERVER_NAME' );
		}
		else if ( filter_var( $sHostName, FILTER_VALIDATE_URL ) ) {
			$sHostName = parse_url( $sRequestedUriPath, PHP_URL_HOST );
		}

		$bSsl = is_ssl() || $this->server( 'HTTP_X_FORWARDED_PROTO' ) == 'https';
		header( 'HTTP/1.1 404 Not Found' );
		$sDie = sprintf(
			'<html><head><title>404 Not Found</title><style type="text/css"></style></head><body><h1>Not Found</h1><p>The requested URL %s was not found on this server.</p><p>Additionally, a 404 Not Found error was encountered while trying to use an ErrorDocument to handle the request.</p><hr><address>Apache Server at %s Port %s</address></body></html>',
			$sRequestedUriPath,
			$sHostName,
			$bSsl ? 443 : $this->server( 'SERVER_PORT' )
		);
		die( $sDie );
	}

	/**
	 * @param string $sStringContent
	 * @param string $sFilename
	 * @return bool
	 */
	public function downloadStringAsFile( $sStringContent, $sFilename ) {
		header( "Content-type: application/octet-stream" );
		header( "Content-disposition: attachment; filename=".$sFilename );
		header( "Content-Transfer-Encoding: binary" );
		header( "Content-Length: ".strlen( $sStringContent ) );
		echo $sStringContent;
		die();
	}

	/**
	 * Use this to reliably read the contents of a PHP file that doesn't have executable
	 * PHP Code.
	 * Why use this? In the name of naive security, silly web hosts can prevent reading the contents of
	 * non-PHP files so we simply put the content we want to have read into a php file and then "include" it.
	 * @param string $sFile
	 * @return string
	 */
	public function readFileContentsUsingInclude( $sFile ) {
		ob_start();
		include( $sFile );
		return ob_get_clean();
	}

	/**
	 * @param      $sKey
	 * @param      $mValue
	 * @param int  $nExpireLength
	 * @param null $sPath
	 * @param null $sDomain
	 * @param bool $bSsl
	 * @return bool
	 */
	public function setCookie( $sKey, $mValue, $nExpireLength = 3600, $sPath = null, $sDomain = null, $bSsl = false ) {
		$_COOKIE[ $sKey ] = $mValue;
		if ( function_exists( 'headers_sent' ) && headers_sent() ) {
			return false;
		}
		return setcookie(
			$sKey,
			$mValue,
			(int)( $this->time() + $nExpireLength ),
			( is_null( $sPath ) && defined( 'COOKIEPATH' ) ) ? COOKIEPATH : $sPath,
			( is_null( $sDomain ) && defined( 'COOKIE_DOMAIN' ) ) ? COOKIE_DOMAIN : $sDomain,
			$bSsl
		);
	}

	/**
	 * @param string $sKey
	 * @return bool
	 */
	public function setDeleteCookie( $sKey ) {
		if ( isset( $_COOKIE[ $sKey ] ) ) {
			unset( $_COOKIE[ $sKey ] );
		}
		return $this->setCookie( $sKey, '', -3600 );
	}

	/**
	 * @return string
	 */
	public function getPhpVersion() {
		return ( defined( 'PHP_VERSION' ) ? PHP_VERSION : phpversion() );
	}

	/**
	 * Cleans out any of the junk that can appear in a PHP version and returns just the 5.4.45
	 * e.g. 5.4.45-0+deb7u5
	 * @return string
	 */
	public function getPhpVersionCleaned() {
		$sVersion = $this->getPhpVersion();
		if ( preg_match( '#^[0-9]{1}\.[0-9]{1}(\.[0-9]{1,3})?#', $sVersion, $aMatches ) ) {
			return $aMatches[ 0 ];
		}
		else {
			return $sVersion;
		}
	}

	/**
	 * @param string $sAtLeastVersion
	 * @return bool
	 */
	public function getPhpVersionIsAtLeast( $sAtLeastVersion ) {
		return version_compare( $this->getPhpVersion(), $sAtLeastVersion, '>=' );
	}

	/**
	 * @return bool
	 */
	public function getPhpSupportsNamespaces() {
		return $this->getPhpVersionIsAtLeast( '5.3' );
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
	 * @return stdClass
	 */
	public function convertArrayToStdClass( $aArray ) {
		$oObject = new stdClass();
		if ( !empty( $aArray ) && is_array( $aArray ) ) {
			foreach ( $aArray as $sKey => $mValue ) {
				$oObject->{$sKey} = $mValue;
			}
		}
		return $oObject;
	}

	/**
	 * @param stdClass $oStdClass
	 * @return array
	 */
	public function convertStdClassToArray( $oStdClass ) {
		return json_decode( json_encode( $oStdClass ), true );
	}

	/**
	 * @param array $aSubjectArray
	 * @param mixed $mValue
	 * @param int   $nDesiredPosition
	 * @return array
	 */
	public function setArrayValueToPosition( $aSubjectArray, $mValue, $nDesiredPosition ) {

		if ( $nDesiredPosition < 0 ) {
			return $aSubjectArray;
		}

		$nMaxPossiblePosition = count( $aSubjectArray ) - 1;
		if ( $nDesiredPosition > $nMaxPossiblePosition ) {
			$nDesiredPosition = $nMaxPossiblePosition;
		}

		$nPosition = array_search( $mValue, $aSubjectArray );
		if ( $nPosition !== false && $nPosition != $nDesiredPosition ) {

			// remove existing and reset index
			unset( $aSubjectArray[ $nPosition ] );
			$aSubjectArray = array_values( $aSubjectArray );

			// insert and update
			// http://stackoverflow.com/questions/3797239/insert-new-item-in-array-on-any-position-in-php
			array_splice( $aSubjectArray, $nDesiredPosition, 0, $mValue );
		}

		return $aSubjectArray;
	}

	/**
	 * note: employs strict search comparison
	 * @param array $aArray
	 * @param mixed $mValue
	 * @param bool  $bFirstOnly - set true to only remove the first element found of this value
	 * @return array
	 */
	public function removeFromArrayByValue( $aArray, $mValue, $bFirstOnly = false ) {
		$aKeys = array();

		if ( $bFirstOnly ) {
			$mKey = array_search( $mValue, $aArray, true );
			if ( $mKey !== false ) {
				$aKeys[] = $mKey;
			}
		}
		else {
			$aKeys = array_keys( $aArray, $mValue, true );
		}

		foreach ( $aKeys as $mKey ) {
			unset( $aArray[ $mKey ] );
		}

		return $aArray;
	}

	/**
	 * Taken from: http://stackoverflow.com/questions/1755144/how-to-validate-domain-name-in-php
	 * @param string $sDomainName
	 * @return bool
	 */
	public function isValidDomainName( $sDomainName ) {
		$sDomainName = trim( $sDomainName );
		return ( preg_match( "/^([a-z\d](-*[a-z\d])*)(\.([a-z\d](-*[a-z\d])*))*$/i", $sDomainName ) //valid chars check
				 && preg_match( "/^.{1,253}$/", $sDomainName ) //overall length check
				 && preg_match( "/^[^\.]{1,63}(\.[^\.]{1,63})*$/", $sDomainName ) );//length of each label
	}

	/**
	 * @return int
	 */
	public function time() {
		return self::GetRequestTime();
	}
}