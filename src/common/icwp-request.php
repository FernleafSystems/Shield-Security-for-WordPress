<?php

class ICWP_WPSF_Request extends ICWP_WPSF_Foundation {

	/**
	 * @var ICWP_WPSF_Request
	 */
	protected static $oInstance = null;

	/**
	 * @var int
	 */
	protected static $nTime = null;

	/**
	 * @var float
	 */
	protected static $nMicroTime = null;

	/**
	 * @var array
	 */
	protected $aRequestUriParts;

	protected function __construct() {
	}

	/**
	 * @return ICWP_WPSF_Request
	 */
	public static function GetInstance() {
		if ( is_null( self::$oInstance ) ) {
			self::$oInstance = new self();
		}
		return self::$oInstance;
	}

	/**
	 * @param string $sKey
	 * @param string $mDefault
	 * @param bool   $bTrim -automatically trim whitespace
	 * @return mixed|null
	 */
	public function cookie( $sKey, $mDefault = null, $bTrim = true ) {
		$mVal = $this->loadDP()->arrayFetch( $_COOKIE, $sKey, $mDefault );
		return ( $bTrim && is_scalar( $mVal ) ) ? trim( $mVal ) : $mVal;
	}

	/**
	 * @param string $sKey
	 * @param mixed  $mDefault
	 * @return mixed|null
	 */
	public function env( $sKey, $mDefault = null ) {
		return $this->loadDP()->arrayFetch( $_ENV, $sKey, $mDefault );
	}

	/**
	 * @param string $sKey
	 * @param null   $mDefault
	 * @param bool   $bTrim -automatically trim whitespace
	 * @return mixed|null
	 */
	public function post( $sKey, $mDefault = null, $bTrim = true ) {
		$mVal = $this->loadDP()->arrayFetch( $_POST, $sKey, $mDefault );
		return ( $bTrim && is_scalar( $mVal ) ) ? trim( $mVal ) : $mVal;
	}

	/**
	 * @param string $sKey
	 * @param null   $mDefault
	 * @param bool   $bTrim -automatically trim whitespace
	 * @return mixed|null
	 */
	public function query( $sKey, $mDefault = null, $bTrim = true ) {
		$mVal = $this->loadDP()->arrayFetch( $_GET, $sKey, $mDefault );
		return ( $bTrim && is_scalar( $mVal ) ) ? trim( $mVal ) : $mVal;
	}

	/**
	 * @param string $sKey
	 * @param null   $mDefault
	 * @param bool   $bTrim -automatically trim whitespace
	 * @return mixed|null
	 */
	public function server( $sKey, $mDefault = null, $bTrim = true ) {
		$mVal = $this->loadDP()->arrayFetch( $_SERVER, $sKey, $mDefault );
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
				$mVal = $this->cookie( $sKey );
			}
		}
		return is_null( $mVal ) ? $mDefault : ( $bTrim && is_scalar( $mVal ) ) ? trim( $mVal ) : $mVal;
	}

	/**
	 * @return string
	 */
	public function getHost() {
		return $this->server( 'HTTP_HOST' );
	}

	/**
	 * @return string
	 */
	public function getMethod() {
		$sRequestMethod = $this->server( 'REQUEST_METHOD' );
		return ( empty( $sRequestMethod ) ? 'get' : strtolower( $sRequestMethod ) );
	}

	/**
	 * @param bool $bIncludeCookie
	 * @return array
	 */
	public function getParams( $bIncludeCookie = true ) {
		$aParams = array_merge( $_GET, $_POST );
		return $bIncludeCookie ? array_merge( $aParams, $_COOKIE ) : $aParams;
	}

	/**
	 * @return string URI Path in lowercase
	 */
	public function getPath() {
		$aRequestParts = $this->getUriParts();
		return $aRequestParts[ 'path' ];
	}

	/**
	 * @return string
	 */
	public function getUri() {
		return $this->server( 'REQUEST_URI' );
	}

	/**
	 * @return array
	 */
	public function getUriParts() {
		if ( !isset( $this->aRequestUriParts ) ) {
			$aExploded = explode( '?', $this->getUri(), 2 );
			$this->aRequestUriParts = array(
				'path'  => empty( $aExploded[ 0 ] ) ? '' : $aExploded[ 0 ],
				'query' => empty( $aExploded[ 1 ] ) ? '' : $aExploded[ 1 ],
			);
		}
		return $this->aRequestUriParts;
	}

	/**
	 * @return string
	 */
	public function getUserAgent() {
		return $this->server( 'HTTP_USER_AGENT' );
	}

	/**
	 * @return string|null
	 */
	public function getScriptName() {
		$sScriptName = $this->server( 'SCRIPT_NAME' );
		return !empty( $sScriptName ) ? $sScriptName : $this->server( 'PHP_SELF' );
	}

	/**
	 * @return bool
	 */
	public function isMethodPost() {
		return ( $this->getMethod() == 'post' );
	}

	/**
	 * TODO: scrap?
	 * Taken from http://www.phacks.net/detecting-search-engine-bot-and-web-spiders/
	 */
	public function isSearchEngineBot() {

		$sUserAgent = $this->server( 'HTTP_USER_AGENT' );
		if ( empty( $sUserAgent ) ) {
			return false;
		}

		$sBots = 'Googlebot|bingbot|Twitterbot|Baiduspider|ia_archiver|R6_FeedFetcher|NetcraftSurveyAgent'
				 .'|Sogou web spider|Yahoo! Slurp|facebookexternalhit|PrintfulBot|msnbot|UnwindFetchor|urlresolver|Butterfly|TweetmemeBot';

		return ( preg_match( "/$sBots/", $sUserAgent ) > 0 );
	}

	/**
	 * @param string $sRequestedUriPath
	 * @param string $sHostName - you can also send a full and valid URL
	 */
	public function sendResponseApache404( $sRequestedUriPath = '', $sHostName = '' ) {
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
	 * @param      $sKey
	 * @param      $mValue
	 * @param int  $nExpireLength
	 * @param null $sPath
	 * @param null $sDomain
	 * @param bool $bSsl
	 * @return bool
	 */
	public function setCookie( $sKey, $mValue, $nExpireLength = 3600, $sPath = null, $sDomain = null, $bSsl = true ) {
		$_COOKIE[ $sKey ] = $mValue;
		if ( function_exists( 'headers_sent' ) && headers_sent() ) {
			return false;
		}
		return setcookie(
			$sKey,
			$mValue,
			(int)( $this->ts() + $nExpireLength ),
			( is_null( $sPath ) && defined( 'COOKIEPATH' ) ) ? COOKIEPATH : $sPath,
			( is_null( $sDomain ) && defined( 'COOKIE_DOMAIN' ) ) ? COOKIE_DOMAIN : $sDomain,
			$bSsl && is_ssl()
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
	 * @return int
	 */
	public function ts() {
		if ( !isset( self::$nTime ) ) {
			self::$nTime = time();
			self::$nMicroTime = function_exists( 'microtime' ) ? @microtime( true ) : false;
		}
		return self::$nTime;
	}

	/**
	 * @param bool $bMillisecondOnly
	 * @return int
	 */
	public function mts( $bMillisecondOnly = false ) {
		$nT = $this->ts();
		if ( empty( self::$nMicroTime ) ) {
			$nT = $bMillisecondOnly ? 0 : $nT;
		}
		else {
			$nT = $bMillisecondOnly ? preg_replace( '#^[0-9]+\.#', '', self::$nMicroTime ) : self::$nMicroTime;
		}
		return $nT;
	}

	/**
	 * alias
	 * @deprecated
	 * @return int
	 */
	public function time() {
		return $this->ts();
	}
}