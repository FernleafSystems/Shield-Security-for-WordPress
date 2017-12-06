<?php
if ( class_exists( 'ICWP_WPSF_WpFunctions', false ) ) {
	return;
}

class ICWP_WPSF_WpIncludes extends ICWP_WPSF_Foundation {

	/**
	 * @var ICWP_WPSF_WpIncludes
	 */
	protected static $oInstance = null;

	/**
	 * @return ICWP_WPSF_WpIncludes
	 */
	public static function GetInstance() {
		if ( is_null( self::$oInstance ) ) {
			self::$oInstance = new self();
		}
		return self::$oInstance;
	}

	public function __construct() {
	}

	/**
	 * @return string
	 */
	public function getUrl_Jquery() {
		return $this->getJsUrl( 'jquery/jquery.js' );
	}

	/**
	 * @param string $sJsInclude
	 * @return string
	 */
	public function getJsUrl( $sJsInclude ) {
		return $this->getIncludeUrl( path_join( 'js', $sJsInclude ) );
	}

	/**
	 * @param string $sInclude
	 * @return string
	 */
	public function getIncludeUrl( $sInclude ) {
		$sInclude = path_join( 'wp-includes', $sInclude );
		return $this->addIncludeModifiedParam( path_join( $this->loadWp()->getWpUrl(), $sInclude ), $sInclude );
	}

	/**
	 * @param $sUrl
	 * @param $sInclude
	 * @return string
	 */
	public function addIncludeModifiedParam( $sUrl, $sInclude ) {
		$nTime = $this->loadFS()->getModifiedTime( path_join( ABSPATH, $sInclude ) );
		return add_query_arg( array( 'mtime' => $nTime ), $sUrl );
	}
}