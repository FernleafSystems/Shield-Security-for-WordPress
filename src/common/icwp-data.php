<?php

class ICWP_WPSF_DataProcessor extends ICWP_WPSF_Foundation {

	/**
	 * @var ICWP_WPSF_DataProcessor
	 */
	protected static $oInstance = null;

	/**
	 * @var int
	 */
	protected static $nRequestTime = null;

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
	 * @param array $aA
	 * @return array
	 */
	public function shuffleArray( $aA ) {
		$aKeys = array_keys( $aA );
		shuffle( $aKeys );
		return array_merge( array_flip( $aKeys ), $aA );
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
	 * @param string $sPrevious
	 * @param string $sNew
	 * @param string $sQueryType
	 * @return bool
	 * @throws \Exception
	 */
	public function isNewVersion( $sPrevious, $sNew, $sQueryType = 'minor' ) {
		if ( substr_count( $sPrevious, '.' ) !== 2 || substr_count( $sNew, '.' ) !== 2 ) {
			throw new \Exception( 'Version not of support type' );
		}
		$sPreviousBranch = implode( '.', array_slice( preg_split( '/[.-]/', $sPrevious ), 0, 2 ) ); // x.y
		$sNewBranch = implode( '.', array_slice( preg_split( '/[.-]/', $sNew ), 0, 2 ) ); // x.y

		$bIsType = false;
		switch ( $sQueryType ) {
			case 'minor':
				$bIsType = ( $sPreviousBranch == $sNew );
				break;
			case 'major':
				$bIsType = version_compare( $sPreviousBranch, $sNewBranch, '<' );
				break;
		}
		return $bIsType;
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
	 * @param bool   $bVerify
	 * @return bool
	 */
	public function isValidUrl( $sUrl, $bVerify = false ) {
		$bValid = filter_var( $sUrl, FILTER_VALIDATE_URL );
		if ( $bValid && $bVerify ) {
			$mRes = $this->loadFS()->getUrl( $sUrl );
			if ( is_array( $mRes ) && isset( $mRes[ 'http_response' ] ) ) {
				/** @var WP_HTTP_Requests_Response $oResp */
				$oResp = $mRes[ 'http_response' ];
				$bValid = $oResp->get_status() >= 200 && $oResp->get_status() < 300;
			}
		}
		return $bValid;
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
	 * Strength can be 1, 3, 7, 15
	 * @param integer $nLength
	 * @param integer $nStrength
	 * @param boolean $bIgnoreAmb
	 * @return string
	 */
	public function generateRandomString( $nLength = 10, $nStrength = 7, $bIgnoreAmb = true ) {
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
	public function generateRandomLetter() {
		$sAtoZ = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
		return $sAtoZ[ wp_rand( 0, ( strlen( $sAtoZ ) - 1 ) ) ];
	}

	/**
	 * @param array  $aA
	 * @param string $sKey
	 * @param mixed  $mDefault
	 * @return mixed|null
	 */
	public function arrayFetch( &$aA, $sKey, $mDefault = null ) {
		return isset( $aA[ $sKey ] ) ? $aA[ $sKey ] : $mDefault;
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
	 * @deprecated
	 * @return int
	 */
	public function time() {
		return $this->loadRequest()->ts();
	}

	/**
	 * @deprecated
	 * @param string $sKey
	 * @param string $mDefault
	 * @param bool   $bTrim -automatically trim whitespace
	 * @return mixed|null
	 */
	public function cookie( $sKey, $mDefault = null, $bTrim = true ) {
		return $this->loadRequest()->cookie( $sKey, $mDefault, $bTrim );
	}

	/**
	 * @deprecated
	 * @param string $sKey
	 * @param mixed  $mDefault
	 * @return mixed|null
	 */
	public function env( $sKey, $mDefault = null ) {
		return $this->loadRequest()->env( $sKey, $mDefault );
	}

	/**
	 * @deprecated
	 * @param string $sKey
	 * @param null   $mDefault
	 * @param bool   $bTrim -automatically trim whitespace
	 * @return mixed|null
	 */
	public function post( $sKey, $mDefault = null, $bTrim = true ) {
		return $this->loadRequest()->post( $sKey, $mDefault, $bTrim );
	}

	/**
	 * @deprecated
	 * @param string $sKey
	 * @param null   $mDefault
	 * @param bool   $bTrim -automatically trim whitespace
	 * @return mixed|null
	 */
	public function query( $sKey, $mDefault = null, $bTrim = true ) {
		return $this->loadRequest()->query( $sKey, $mDefault, $bTrim );
	}

	/**
	 * @deprecated
	 * @param string $sKey
	 * @param null   $mDefault
	 * @param bool   $bTrim -automatically trim whitespace
	 * @return mixed|null
	 */
	public function server( $sKey, $mDefault = null, $bTrim = true ) {
		return $this->loadRequest()->server( $sKey, $mDefault, $bTrim );
	}

	/**
	 * @deprecated
	 * @param string $sKey
	 * @param null   $mDefault
	 * @param bool   $bIncludeCookie
	 * @param bool   $bTrim -automatically trim whitespace
	 * @return mixed|null
	 */
	public function request( $sKey, $bIncludeCookie = false, $mDefault = null, $bTrim = true ) {
		return $this->loadRequest()->request( $sKey, $bIncludeCookie, $mDefault, $bTrim );
	}

	/**
	 * @deprecated
	 * @return string URI Path in lowercase
	 */
	public function getRequestPath() {
		return $this->loadRequest()->getPath();
	}

	/**
	 * @deprecated
	 * @return string
	 */
	public function getRequestUri() {
		return $this->loadRequest()->getUri();
	}

	/**
	 * @deprecated
	 * @return string
	 */
	public function getUserAgent() {
		return $this->loadRequest()->getUserAgent();
	}

	/**
	 * @deprecated
	 * @param bool $bIncludeCookie
	 * @return array
	 */
	public function getRequestParams( $bIncludeCookie = true ) {
		return $this->loadRequest()->getParams( $bIncludeCookie );
	}

	/**
	 * @deprecated
	 * @return array
	 */
	public function getRequestUriParts() {
		return $this->loadRequest()->getUriParts();
	}

	/**
	 * @deprecated
	 * @return string
	 */
	public function getRequestMethod() {
		return $this->loadRequest()->getMethod();
	}

	/**
	 * @deprecated
	 * @return bool
	 */
	public function isMethodPost() {
		return $this->loadRequest()->isMethodPost();
	}

	/**
	 * @deprecated
	 * @return string|null
	 */
	public function getScriptName() {
		return $this->loadRequest()->getScriptName();
	}

	/**
	 * @deprecated
	 * @param string $sRequestedUriPath
	 * @param string $sHostName - you can also send a full and valid URL
	 */
	public function doSendApache404( $sRequestedUriPath = '', $sHostName = '' ) {
		return $this->loadRequest()->sendResponseApache404( $sRequestedUriPath, $sHostName );
	}

	/**
	 * @deprecated
	 * @param      $sKey
	 * @param      $mValue
	 * @param int  $nExpireLength
	 * @param null $sPath
	 * @param null $sDomain
	 * @param bool $bSsl
	 * @return bool
	 */
	public function setCookie( $sKey, $mValue, $nExpireLength = 3600, $sPath = null, $sDomain = null, $bSsl = true ) {
		return $this->loadRequest()->setCookie( $sKey, $mValue, $nExpireLength, $sPath, $sDomain, $bSsl );
	}

	/**
	 * @deprecated
	 * @param string $sKey
	 * @return bool
	 */
	public function setDeleteCookie( $sKey ) {
		return $this->loadRequest()->setDeleteCookie( $sKey );
	}
}