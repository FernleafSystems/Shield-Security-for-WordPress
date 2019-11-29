<?php

use FernleafSystems\Wordpress\Services\Services;

/**
 * Class ICWP_WPSF_WpAdminNotices
 * @deprecated 8.4
 */
class ICWP_WPSF_WpAdminNotices extends ICWP_WPSF_Foundation {

	/**
	 * @var ICWP_WPSF_WpAdminNotices
	 */
	protected static $oInstance = null;

	/**
	 * @var string
	 */
	protected $sFlashMessage;

	/**
	 * @var string
	 */
	protected $sPrefix = '';

	/**
	 * @return ICWP_WPSF_WpAdminNotices
	 */
	public static function GetInstance() {
		if ( is_null( self::$oInstance ) ) {
			self::$oInstance = new self();
		}
		return self::$oInstance;
	}

	protected function __construct() {
	}

	public function onWpAdminNotices() {
	}

	/**
	 * @return \FernleafSystems\Wordpress\Services\Utilities\PluginUserMeta
	 * @throws \Exception
	 */
	protected function getCurrentUserMeta() {
		return Services::WpUsers()->metaVoForUser( rtrim( $this->getPrefix(), '-' ) );
	}

	/**
	 * @return string
	 */
	public function getPrefix() {
		return $this->sPrefix;
	}

	/**
	 * @return string
	 */
	protected function getFlash() {
		return $this->sFlashMessage;
	}

	/**
	 * @return array
	 */
	protected function getFlashParts() {
		return explode( '::', $this->getFlash(), 3 );
	}

	/**
	 * @return string
	 */
	public function getFlashText() {
		$aParts = $this->getFlashParts();
		return isset( $aParts[ 1 ] ) ? $aParts[ 1 ] : '';
	}

	/**
	 * @return $this
	 */
	public function flushFlash() {
		if ( Services::WpUsers()->isUserLoggedIn() ) {
			$oMeta = $this->getCurrentUserMeta();
			if ( isset( $oMeta->flash_msg ) ) {
				$this->sFlashMessage = (string)$oMeta->flash_msg;
				unset( $oMeta->flash_msg );
			}
		}
		return $this;
	}
}