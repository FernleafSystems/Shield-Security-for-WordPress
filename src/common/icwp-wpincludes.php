<?php

class ICWP_WPSF_WpIncludes extends ICWP_WPSF_Foundation {

	/**
	 * @var ICWP_WPSF_WpIncludes
	 */
	protected static $oInstance = null;

	/**
	 * @var array
	 */
	private $aScriptTags;

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

	/**
	 * Supports PHP 5.3+
	 * @param string $sIncludeHandle
	 * @param string $sAttribute
	 * @param string $sValue
	 * @return $this
	 */
	public function addIncludeAttribute( $sIncludeHandle, $sAttribute, $sValue ) {
		if ( empty( $this->aScriptTags ) ) {
			$this->aScriptTags = array();
		}

		$this->aScriptTags[ $sIncludeHandle ] = $sAttribute.'::'.$sValue;

		// adjusted to use php5.2 compatible
		add_filter( 'script_loader_tag', array( $this, 'filterScriptTags' ), 10, 2 );
//		if ( $this->loadDP()->getPhpVersionIsAtLeast( '5.3' ) ) {
//
//			add_filter( 'script_loader_tag',
//				function ( $sTag, $sHandle ) use ( $sIncludeHandle, $sAttribute, $sValue ) {
//					if ( $sHandle == $sIncludeHandle && strpos( $sTag, $sAttribute.'=' ) === false ) {
//						$sTag = str_replace( ' src', sprintf( ' %s="%s" src', $sAttribute, $sValue ), $sTag );
//					}
//					return $sTag;
//				},
//				10, 2
//			);
//		}
		return $this;
	}

	/**
	 * This is the crappy php 5.2 method of script_loader_tag because anon functions aren't supported.
	 * @param $sTag
	 * @param $sHandle
	 * @return mixed
	 */
	public function filterScriptTags( $sTag, $sHandle ) {
		if ( isset( $this->aScriptTags[ $sHandle ] ) ) {
			list( $sAttribute, $sValue ) = explode( '::', $this->aScriptTags[ $sHandle ] );
			if ( strpos( $sTag, $sAttribute.'=' ) === false ) {
				$sTag = str_replace( ' src', sprintf( ' %s="%s" src', $sAttribute, $sValue ), $sTag );
			}
		}
		return $sTag;
	}
}