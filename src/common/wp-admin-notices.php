<?php

class ICWP_WPSF_WpAdminNotices extends ICWP_WPSF_Foundation {

	/**
	 * @var ICWP_WPSF_WpAdminNotices
	 */
	protected static $oInstance = null;

	/**
	 * @var array
	 */
	protected $aAdminNotices;

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
		$this->sFlashMessage = '';
		add_action( 'admin_notices', array( $this, 'onWpAdminNotices' ) );
		add_action( 'network_admin_notices', array( $this, 'onWpAdminNotices' ) );
	}

	/**
	 * @param array $aAjaxResponse
	 * @return array
	 */
	public function handleAuthAjax( $aAjaxResponse ) {
		if ( empty( $aAjaxResponse ) && $this->loadRequest()->request( 'exec' ) === 'dismiss_admin_notice' ) {
			$aAjaxResponse = $this->ajaxExec_DismissAdminNotice();
		}
		return $aAjaxResponse;
	}

	/**
	 * @return array
	 */
	protected function ajaxExec_DismissAdminNotice() {
		// Get all notices and if this notice exists, we set it to "hidden"
		$sNoticeId = sanitize_key( $this->loadRequest()->query( 'notice_id', '' ) );
		$aNotices = apply_filters( $this->getPrefix().'register_admin_notices', array() );
		if ( !empty( $sNoticeId ) && array_key_exists( $sNoticeId, $aNotices ) ) {
			$this->setMeta( $aNotices[ $sNoticeId ][ 'id' ] );
		}
		return array( 'success' => true );
	}

	public function onWpAdminNotices() {
		do_action( $this->getPrefix().'generate_admin_notices' );
		foreach ( $this->getNotices() as $sKey => $sAdminNoticeContent ) {
			echo $sAdminNoticeContent;
		}
		$this->flashUserAdminNotice();
	}

	/**
	 * @param string $sNoticeId
	 * @return true
	 */
	public function isDismissed( $sNoticeId ) {
		$aMeta = $this->getMeta( $sNoticeId );
		return ( isset( $aMeta[ 'time' ] ) && $aMeta[ 'time' ] > 0 );
	}

	/**
	 * @param string $sNoticeId
	 * @return false|string
	 */
	public function getMeta( $sNoticeId ) {
		$mValue = array();

		$oMeta = $this->getCurrentUserMeta();

		$sCleanNotice = 'notice_'.str_replace( array( '-', '_' ), '', $sNoticeId );
		if ( isset( $oMeta->{$sCleanNotice} ) && is_array( $oMeta->{$sCleanNotice} ) ) {
			$mValue = $oMeta->{$sCleanNotice};
		}

		return $mValue;
	}

	/**
	 * @return ICWP_UserMeta
	 */
	protected function getCurrentUserMeta() {
		return $this->loadWpUsers()->metaVoForUser( rtrim( $this->getPrefix(), '-' ) );
	}

	/**
	 * @param string $sNoticeId
	 * @param array  $aMeta
	 */
	public function setMeta( $sNoticeId, $aMeta = array() ) {
		if ( !is_array( $aMeta ) ) {
			$aMeta = array();
		}

		$oMeta = $this->getCurrentUserMeta();
		$sCleanNotice = 'notice_'.str_replace( array( '-', '_' ), '', $sNoticeId );
		$oMeta->{$sCleanNotice} = array_merge( array( 'time' => $this->loadRequest()->ts() ), $aMeta );
		return;
	}

	/**
	 * @return string
	 */
	public function getPrefix() {
		return $this->sPrefix;
	}

	/**
	 * @param string $sPrefix
	 * @return $this
	 */
	public function setPrefix( $sPrefix ) {
		$this->sPrefix = rtrim( $sPrefix, '-' ).'-';
		return $this;
	}

	/**
	 * @return array
	 */
	protected function getNotices() {
		if ( !isset( $this->aAdminNotices ) || !is_array( $this->aAdminNotices ) ) {
			$this->aAdminNotices = array();
		}
		return $this->aAdminNotices;
	}

	/**
	 * @param string $sNoticeId
	 * @return bool
	 */
	protected function getNoticeAlreadyExists( $sNoticeId ) {
		return !empty( $this->aAdminNotices[ $sNoticeId ] );
	}

	/**
	 * @param string $sNoticeId
	 * @param string $sNotice
	 * @return $this
	 */
	public function addAdminNotice( $sNotice, $sNoticeId = '' ) {
		if ( !empty( $sNotice ) ) {
			if ( empty( $sNoticeId ) ) {
				$sNoticeId = md5( uniqid( '', true ) );
			}
			if ( !$this->getNoticeAlreadyExists( $sNoticeId ) ) {
				$aCurrentNotices = $this->getNotices();
				$aCurrentNotices[ $sNoticeId ] = $sNotice;
				$this->aAdminNotices = $aCurrentNotices;
			}
		}
		return $this;
	}

	/**
	 * Use this to add a simple message to the admin notice collection. It'll wrap it up in basic html
	 * @param string $sRawMessage
	 * @param string $sType
	 * @return $this
	 */
	public function addRawAdminNotice( $sRawMessage, $sType = 'updated' ) {
		return $this->addAdminNotice( $this->wrapAdminNoticeHtml( $sRawMessage, $sType ) );
	}

	/**
	 * Provides the basic HTML template for printing a WordPress Admin Notices
	 * @param $sNotice       - The message to be displayed.
	 * @param $sMessageClass - either error or updated
	 * @param $bPrint        - if true, will echo. false will return the string
	 * @return boolean|string
	 */
	protected function wrapAdminNoticeHtml( $sNotice = '', $sMessageClass = 'updated', $bPrint = false ) {
		$sWrapper = '<div class="%s odp-admin-notice">%s</div>';
		$sFullNotice = sprintf( $sWrapper, $sMessageClass, $sNotice );
		if ( $bPrint ) {
			echo $sFullNotice;
			return true;
		}
		else {
			return $sFullNotice;
		}
	}

	/**
	 * @param string $sMessage
	 * @param bool   $bError
	 * @return $this
	 */
	public function addFlashUserMessage( $sMessage, $bError = false ) {
		if ( $this->loadWpUsers()->isUserLoggedIn() ) {
			$this->getCurrentUserMeta()->flash_msg = ( $bError ? 'error' : 'updated' )
													 .'::'.sanitize_text_field( $sMessage )
													 .'::'.( $this->loadRequest()->ts() + 300 );
		}
		return $this;
	}

	protected function flashUserAdminNotice() {
		$this->flushFlash();
		if ( $this->hasFlash() ) {
			$aParts = $this->getFlashParts();
			if ( empty( $aParts[ 2 ] ) || $this->loadRequest()->ts() < $aParts[ 2 ] ) {
				echo $this->wrapAdminNoticeHtml( '<p>'.$aParts[ 1 ].'</p>', $aParts[ 0 ] );
			}
		}
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
	 * Requires that the flash has been flushed
	 * @return bool
	 */
	public function hasFlash() {
		$sFlash = $this->getFlash();
		return !empty( $sFlash );
	}

	/**
	 * @return $this
	 */
	public function flushFlash() {
		if ( $this->loadWpUsers()->isUserLoggedIn() ) {
			$oMeta = $this->getCurrentUserMeta();
			if ( isset( $oMeta->flash_msg ) ) {
				$this->sFlashMessage = (string)$oMeta->flash_msg;
				unset( $oMeta->flash_msg );
			}
		}
		return $this;
	}
}