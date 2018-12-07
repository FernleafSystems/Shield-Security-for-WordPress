<?php

if ( class_exists( 'ICWP_WPSF_Processor_Base', false ) ) {
	return;
}

abstract class ICWP_WPSF_Processor_Base extends ICWP_WPSF_Foundation {

	/**
	 * @var ICWP_WPSF_FeatureHandler_Base
	 */
	protected $oModCon;

	/**
	 * @var int
	 */
	static protected $nPromoNoticesCount = 0;

	/**
	 * @var ICWP_WPSF_Processor_Base[]
	 */
	protected $aSubProcessors;

	/**
	 * @var bool
	 */
	private $bLoginCaptured;

	/**
	 * @param ICWP_WPSF_FeatureHandler_Base $oModCon
	 */
	public function __construct( $oModCon ) {
		$this->oModCon = $oModCon;

		add_action( 'init', array( $this, 'onWpInit' ) );
		add_action( 'wp_login', array( $this, 'onWpLogin' ), 10, 2 );
		add_action( 'set_logged_in_cookie', array( $this, 'onWpSetLoggedInCookie' ), 5, 4 );
		add_action( $oModCon->prefix( 'plugin_shutdown' ), array( $this, 'onModuleShutdown' ) );

		$this->init();
	}

	public function onWpInit() {
		$oMod = $this->getMod();
		$oCon = $oMod->getConn();
		if ( $oCon->isValidAdminArea() && $oCon->isPluginAdmin() ) {
			add_action( $oMod->prefix( 'generate_admin_notices' ), array( $this, 'autoAddToAdminNotices' ) );
			if ( method_exists( $this, 'addToAdminNotices' ) ) {
				add_action( $oMod->prefix( 'generate_admin_notices' ), array( $this, 'addToAdminNotices' ) );
			}
		}
	}

	/**
	 * @param string  $sUsername
	 * @param WP_User $oUser
	 */
	public function onWpLogin( $sUsername, $oUser ) {
		/*
		if ( !$oUser instanceof WP_User ) {
			$oUser = $this->loadWpUsers()->getUserByUsername( $sUsername );
		}
		*/
	}

	/**
	 * @param string $sCookie
	 * @param int    $nExpire
	 * @param int    $nExpiration
	 * @param int    $nUserId
	 */
	public function onWpSetLoggedInCookie( $sCookie, $nExpire, $nExpiration, $nUserId ) {
	}

	/**
	 * @return bool
	 */
	protected function isLoginCaptured() {
		return (bool)$this->bLoginCaptured;
	}

	/**
	 * @return $this
	 */
	protected function setLoginCaptured() {
		$this->bLoginCaptured = true;
		return $this;
	}

	/**
	 * @return int
	 */
	protected function getPromoNoticesCount() {
		return self::$nPromoNoticesCount;
	}

	/**
	 * @return $this
	 */
	protected function incrementPromoNoticesCount() {
		self::$nPromoNoticesCount++;
		return $this;
	}

	/**
	 * @return ICWP_WPSF_Plugin_Controller
	 */
	public function getController() {
		return $this->getMod()->getConn();
	}

	public function autoAddToAdminNotices() {
		foreach ( $this->getMod()->getAdminNotices() as $sNoticeId => $aAttrs ) {

			if ( !$this->getIfDisplayAdminNotice( $aAttrs ) ) {
				continue;
			}

			$sMethodName = 'addNotice_'.str_replace( '-', '_', $sNoticeId );
			if ( method_exists( $this, $sMethodName ) ) {
				$aAttrs[ 'id' ] = $sNoticeId;
				$aAttrs[ 'notice_id' ] = $sNoticeId;
				call_user_func( array( $this, $sMethodName ), $aAttrs );
			}
		}
	}

	/**
	 * @param array $aAttrs
	 * @return bool
	 */
	protected function getIfDisplayAdminNotice( $aAttrs ) {
		$oWpNotices = $this->loadWpNotices();

		if ( empty( $aAttrs[ 'schedule' ] )
			 || !in_array( $aAttrs[ 'schedule' ], array( 'once', 'conditions', 'version', 'never' ) ) ) {
			$aAttrs[ 'schedule' ] = 'conditions';
		}

		if ( $aAttrs[ 'schedule' ] == 'never' ) {
			return false;
		}

		if ( $aAttrs[ 'schedule' ] == 'once'
			 && ( !$this->loadWpUsers()->canSaveMeta() || $oWpNotices->isDismissed( $aAttrs[ 'id' ] ) )
		) {
			return false;
		}

		if ( isset( $aAttrs[ 'type' ] ) && $aAttrs[ 'type' ] == 'promo' ) {
			if ( $this->loadWp()->isMobile() ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * @deprecated remove next release
	 */
	public function action_doFeatureProcessorShutdown() {
	}

	public function onModuleShutdown() {
	}

	/**
	 */
	public function init() {
	}

	/**
	 * @return bool
	 */
	public function isReadyToRun() {
		return true;
	}

	/**
	 * Override to set what this processor does when it's "run"
	 */
	abstract public function run();

	/**
	 * @param array $aNoticeData
	 * @throws Exception
	 */
	protected function insertAdminNotice( $aNoticeData ) {
		$aAttrs = $aNoticeData[ 'notice_attributes' ];
		$bIsPromo = isset( $aAttrs[ 'type' ] ) && $aAttrs[ 'type' ] == 'promo';
		if ( $bIsPromo && $this->getPromoNoticesCount() > 0 ) {
			return;
		}

		$bCantDismiss = isset( $aNoticeData[ 'notice_attributes' ][ 'can_dismiss' ] )
						&& !$aNoticeData[ 'notice_attributes' ][ 'can_dismiss' ];

		$oNotices = $this->loadWpNotices();
		if ( $bCantDismiss || !$oNotices->isDismissed( $aAttrs[ 'id' ] ) ) {

			$sRenderedNotice = $this->getMod()->renderAdminNotice( $aNoticeData );
			if ( !empty( $sRenderedNotice ) ) {
				$oNotices->addAdminNotice(
					$sRenderedNotice,
					$aNoticeData[ 'notice_attributes' ][ 'notice_id' ]
				);
				if ( $bIsPromo ) {
					$this->incrementPromoNoticesCount();
				}
			}
		}
	}

	/**
	 * @param       $sOptionKey
	 * @param mixed $mDefault
	 * @return mixed
	 */
	public function getOption( $sOptionKey, $mDefault = false ) {
		return $this->getMod()->getOpt( $sOptionKey, $mDefault );
	}

	/**
	 * We don't handle locale derivatives (yet)
	 * @return string
	 */
	protected function getGoogleRecaptchaLocale() {
		return str_replace( '_', '-', $this->loadWp()->getLocale() );
	}

	/**
	 * @return ICWP_WPSF_Processor_Email
	 */
	public function getEmailProcessor() {
		return $this->getMod()->getEmailProcessor();
	}

	/**
	 * @return ICWP_WPSF_FeatureHandler_Base
	 */
	protected function getMod() {
		return $this->oModCon;
	}

	/**
	 * @deprecated
	 * @return ICWP_WPSF_FeatureHandler_Base
	 */
	protected function getFeature() {
		return $this->getMod();
	}

	/**
	 * @param string $sKey
	 * @return ICWP_WPSF_Processor_Base|null
	 */
	protected function getSubProcessor( $sKey ) {
		$aProcessors = $this->getSubProcessors();
		return isset( $aProcessors[ $sKey ] ) ? $aProcessors[ $sKey ] : null;
	}

	/**
	 * @return ICWP_WPSF_Processor_Base[]
	 */
	protected function getSubProcessors() {
		if ( !isset( $this->aSubProcessors ) ) {
			$this->aSubProcessors = array();
		}
		return $this->aSubProcessors;
	}

	/**
	 * @return ICWP_UserMeta
	 */
	protected function getCurrentUserMeta() {
		return $this->getController()->getCurrentUserMeta();
	}

	/**
	 * Will prefix and return any string with the unique plugin prefix.
	 * @param string $sSuffix
	 * @param string $sGlue
	 * @return string
	 */
	protected function prefix( $sSuffix = '', $sGlue = '-' ) {
		return $this->getMod()->prefix( $sSuffix, $sGlue );
	}

	/**
	 * @return bool|int|string
	 */
	protected function ip() {
		return $this->loadIpService()->getRequestIp();
	}

	/**
	 * @return int
	 */
	protected function time() {
		return $this->loadRequest()->ts();
	}

	/**
	 * @deprecated
	 * @param string  $sKey
	 * @param mixed   $mValueToTest
	 * @param boolean $bStrict
	 * @return bool
	 */
	public function getIsOption( $sKey, $mValueToTest, $bStrict = false ) {
		return $this->getMod()->isOpt( $sKey, $mValueToTest, $bStrict );
	}
}