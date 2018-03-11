<?php
if ( class_exists( 'ICWP_WPSF_WpAdminNotices', false ) ) {
	return;
}

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
	protected $sActionPrefix = '';

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
		add_action( 'admin_notices', array( $this, 'onWpAdminNotices' ) );
		add_action( 'network_admin_notices', array( $this, 'onWpAdminNotices' ) );
		add_action( 'wp_loaded', array( $this, 'flushFlashMessage' ) );
	}

	/**
	 * @param array $aAjaxResponse
	 * @return array
	 */
	public function handleAuthAjax( $aAjaxResponse ) {
		if ( empty( $aAjaxResponse ) && $this->loadDP()->request( 'exec' ) === 'dismiss_admin_notice' ) {
			$aAjaxResponse = $this->ajaxExec_DismissAdminNotice();
		}
		return $aAjaxResponse;
	}

	/**
	 * @return array
	 */
	protected function ajaxExec_DismissAdminNotice() {
		// Get all notices and if this notice exists, we set it to "hidden"
		$sNoticeId = sanitize_key( $this->loadDP()->query( 'notice_id', '' ) );
		$aNotices = apply_filters( $this->getActionPrefix().'register_admin_notices', array() );
		if ( !empty( $sNoticeId ) && array_key_exists( $sNoticeId, $aNotices ) ) {
			$this->setMeta( $aNotices[ $sNoticeId ][ 'id' ] );
		}
		return array( 'success' => true );
	}

	public function onWpAdminNotices() {
		do_action( $this->getActionPrefix().'generate_admin_notices' );
		foreach ( $this->getNotices() as $sKey => $sAdminNoticeContent ) {
			echo $sAdminNoticeContent;
		}
		$this->flashNotice();
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

		$oMeta = $this->loadWpUsers()->metaVoForUser( rtrim( $this->getActionPrefix(), '-' ) );

		$sCleanNotice = 'notice_'.str_replace( array( '-', '_' ), '', $sNoticeId );
		if ( isset( $oMeta->{$sCleanNotice} ) && is_array( $oMeta->{$sCleanNotice} ) ) {
			$mValue = $oMeta->{$sCleanNotice};
		}
		else {
			$oWp = $this->loadWpUsers();
			$mOldValue = $oWp->getUserMeta( $this->getActionPrefix().$sNoticeId );
			if ( !empty( $mOldValue ) ) {
				$oWp->deleteUserMeta( $this->getActionPrefix().$sNoticeId );
				$this->setMeta( $sNoticeId );
				$mValue = $oMeta->{$sCleanNotice};
			}
		}

		return $mValue;
	}

	/**
	 * @param string $sNoticeId
	 * @param array  $aMeta
	 */
	public function setMeta( $sNoticeId, $aMeta = array() ) {
		if ( !is_array( $aMeta ) ) {
			$aMeta = array();
		}

		$oMeta = $this->loadWpUsers()->metaVoForUser( rtrim( $this->getActionPrefix(), '-' ) );
		$sCleanNotice = 'notice_'.str_replace( array( '-', '_' ), '', $sNoticeId );
		$oMeta->{$sCleanNotice} = array_merge( array( 'time' => $this->loadDP()->time() ), $aMeta );
		return;
	}

	/**
	 * @return string
	 */
	public function getActionPrefix() {
		return $this->sActionPrefix;
	}

	/**
	 * @param string $sPrefix
	 * @return $this
	 */
	public function setActionPrefix( $sPrefix ) {
		$this->sActionPrefix = rtrim( $sPrefix, '-' ).'-';
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
		$sWrapper = '<div class="%s icwp-admin-notice">%s</div>';
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
	 */
	public function addFlashErrorMessage( $sMessage ) {
		$this->addFlashMessage( $sMessage, 'error' );
	}

	/**
	 * @param string $sMessage
	 * @param string $sType
	 */
	public function addFlashMessage( $sMessage, $sType = 'updated' ) {
		$this->loadDP()->setCookie(
			$this->getActionPrefix().'flash', $sType.'::'.esc_attr( $sMessage )
		);
	}

	protected function flashNotice() {
		if ( !empty( $this->sFlashMessage ) ) {
			$aParts = explode( '::', $this->sFlashMessage, 2 );
			echo $this->wrapAdminNoticeHtml( $aParts[ 1 ], $aParts[ 0 ] );
		}
	}

	/**
	 * @return string
	 */
	public function getRawFlashMessageText() {
		$aParts = explode( '::', $this->sFlashMessage, 2 );
		return isset( $aParts[ 1 ] ) ? $aParts[ 1 ] : '';
	}

	/**
	 * @return $this
	 */
	public function flushFlashMessage() {

		$oDp = $this->loadDataProcessor();
		$sCookieName = $this->getActionPrefix().'flash';
		$this->sFlashMessage = $oDp->FetchCookie( $sCookieName, '' );
		if ( !empty( $this->sFlashMessage ) ) {
			$this->sFlashMessage = sanitize_text_field( $this->sFlashMessage );
		}
		$oDp->setDeleteCookie( $sCookieName );
		return $this;
	}
}