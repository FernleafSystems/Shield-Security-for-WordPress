<?php

if ( !class_exists( 'ICWP_WPSF_WpAdminNotices', false ) ):

	class ICWP_WPSF_WpAdminNotices extends ICWP_WPSF_Foundation {

		/**
		 * @var ICWP_WPSF_WpAdminNotices
		 */
		protected static $oInstance = NULL;

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
			add_action( 'admin_notices',			array( $this, 'onWpAdminNotices' ) );
			add_action( 'network_admin_notices',	array( $this, 'onWpAdminNotices' ) );
			add_action( 'wp_loaded',				array( $this, 'flushFlashMessage' ) );

			if ( $this->loadWpFunctionsProcessor()->getIsAjax() ) {
				add_action( 'wp_ajax_icwp_DismissAdminNotice', array( $this, 'ajaxDismissAdminNotice' ) );
			}
		}

		public function onWpAdminNotices() {
			do_action( $this->getActionPrefix().'generate_admin_notices' );
			foreach( $this->getNotices() as $sKey => $sAdminNoticeContent ) {
				echo $sAdminNoticeContent;
			}
			$this->flashNotice();
		}

		public function ajaxDismissAdminNotice() {

			$bSuccess = $this->checkAjaxNonce();
			if ( $bSuccess ) {
				// Get all notices and if this notice exists, we set it to "hidden"
				$sNoticeId = sanitize_key( $this->loadDataProcessor()->FetchGet( 'notice_id', '' ) );
				$aNotices = apply_filters( $this->getActionPrefix().'register_admin_notices', array() );
				if ( !empty( $sNoticeId ) && array_key_exists( $sNoticeId, $aNotices ) ) {
					$this->setAdminNoticeAsDismissed( $aNotices[ $sNoticeId ] );
				}
				$this->sendAjaxResponse( true );
			}
		}

		/**
		 * @param string $sNoticeId
		 * @return true
		 */
		public function getAdminNoticeIsDismissed( $sNoticeId ) {
			$sCurrentMetaValue = $this->getAdminNoticeMeta( $sNoticeId );
			return ( $sCurrentMetaValue == 'Y' );
		}

		/**
		 * @param string $sNoticeId
		 * @return false|string
		 */
		public function getAdminNoticeMeta( $sNoticeId ) {
			return $this->loadWpUsersProcessor()->getUserMeta( $this->getActionPrefix().$sNoticeId );
		}

		/**
		 * @param array $aNotice
		 */
		public function setAdminNoticeAsDismissed( $aNotice ) {
			$this->loadWpUsersProcessor()->updateUserMeta( $this->getActionPrefix().$aNotice['id'], 'Y' );
		}

		/**
		 * Will send ajax error response immediately upon failure
		 * @return bool
		 */
		protected function checkAjaxNonce() {

			$sNonce = $this->loadDataProcessor()->FetchRequest( '_ajax_nonce', '' );
			if ( empty( $sNonce ) ) {
				$sMessage = 'Nonce security checking failed - the nonce value was empty.';
			}
			else if ( wp_verify_nonce( $sNonce, 'icwp_ajax' ) === false ) {
				$sMessage = sprintf( 'Nonce security checking failed - the nonce supplied was "%s".', $sNonce );
			}
			else {
				return true; // At this stage we passed the nonce check
			}

			// At this stage we haven't returned after success so we failed the nonce check
			$this->sendAjaxResponse( false, array( 'message' => $sMessage ) );
			return false; //unreachable
		}

		/**
		 * @param $bSuccess
		 * @param array $aData
		 */
		protected function sendAjaxResponse( $bSuccess, $aData = array() ) {
			$bSuccess ? wp_send_json_success( $aData ) : wp_send_json_error( $aData );
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
		 * @param string $sNotice
		 * @return $this
		 */
		public function addAdminNotice( $sNotice, $sNoticeId = '' ) {
			if ( !empty( $sNotice ) ) {
				$aCurrentNotices = $this->getNotices();
				if ( empty( $sNoticeId ) ) {
					$sNoticeId = md5( uniqid( '', true ) );
				}
				$aCurrentNotices[ $sNoticeId ] = $sNotice;
				$this->aAdminNotices = $aCurrentNotices;
			}
			return $this;
		}

		/**
		 * Use this to add a simple message to the admin notice collection. It'll wrap it up in basic html
		 *
		 * @param string $sRawMessage
		 * @param string $sType
		 * @return $this
		 */
		public function addRawAdminNotice( $sRawMessage, $sType = 'updated' ) {
			return $this->addAdminNotice( $this->wrapAdminNoticeHtml( $sRawMessage, $sType ) );
		}

		/**
		 * Provides the basic HTML template for printing a WordPress Admin Notices
		 *
		 * @param $sNotice - The message to be displayed.
		 * @param $sMessageClass - either error or updated
		 * @param $bPrint - if true, will echo. false will return the string
		 *
		 * @return boolean|string
		 */
		protected function wrapAdminNoticeHtml( $sNotice = '', $sMessageClass = 'updated', $bPrint = false ) {
			$sWrapper = '<div class="%s icwp-admin-notice">%s</div>';
			$sFullNotice = sprintf( $sWrapper, $sMessageClass, $sNotice );
			if ( $bPrint ) {
				echo $sFullNotice;
				return true;
			} else {
				return $sFullNotice;
			}
		}

		/**
		 * @param $sMessage
		 */
		public function addFlashMessage( $sMessage ) {
			$this->loadDataProcessor()->setCookie( $this->getActionPrefix().'flash', esc_attr( $sMessage ) );
		}

		protected function flashNotice() {
			if ( !empty( $this->sFlashMessage ) ) {
				echo $this->wrapAdminNoticeHtml( $this->sFlashMessage );
			}
		}

		public function flushFlashMessage() {

			$oDp = $this->loadDataProcessor();
			$sCookieName = $this->getActionPrefix().'flash';
			$this->sFlashMessage = $oDp->FetchCookie( $sCookieName, '' );
			if ( !empty( $this->sFlashMessage ) ) {
				$this->sFlashMessage = sanitize_text_field( $this->sFlashMessage );
			}
			$oDp->setDeleteCookie( $sCookieName );
		}
	}
endif;