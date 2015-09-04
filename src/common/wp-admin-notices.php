<?php

if ( !class_exists( 'ICWP_WPSF_WpAdminNotices_V1', false ) ):

	class ICWP_WPSF_WpAdminNotices_V1 {

		/**
		 * @var ICWP_WPSF_WpAdminNotices_V1
		 */
		protected static $oInstance = NULL;

		/**
		 * @var array
		 */
		protected $aAdminNotices;

		/**
		 * @var string
		 */
		protected $sActionPrefix = '';

		protected function __construct() {
			add_action( 'admin_notices',			array( $this, 'onWpAdminNotices' ) );
			add_action( 'network_admin_notices',	array( $this, 'onWpAdminNotices' ) );
		}

		public function onWpAdminNotices() {
			do_action( $this->getActionPrefix().'generate_admin_notices' );
			foreach( $this->getNotices() as $sAdminNotice ) {
				echo $sAdminNotice;
			}
			$this->flashNotice();
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
		 * @return ICWP_WPSF_WpAdminNotices_V1
		 */
		public static function GetInstance() {
			if ( is_null( self::$oInstance ) ) {
				self::$oInstance = new self();
			}
			return self::$oInstance;
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
		 * @param string $sNotice
		 * @return $this
		 */
		public function addAdminNotice( $sNotice ) {
			if ( !empty( $sNotice ) ) {
				$aCurrentNotices = $this->getNotices();
				$aCurrentNotices[] = $sNotice;
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
			$sMessage = $this->readFlashMessage();
			if ( !empty( $sMessage ) ) {
				echo $this->wrapAdminNoticeHtml( $sMessage );
			}
		}

		protected function readFlashMessage() {

			$oDp = $this->loadDataProcessor();
			$sCookieName = $this->getActionPrefix().'flash';
			$sMessage = $oDp->FetchCookie( $sCookieName, '' );
			if ( !empty( $sMessage ) ) {
				$sMessage = sanitize_text_field( $sMessage );
			}
			$oDp->setDeleteCookie( $sCookieName );
			return $sMessage;
		}

		/**
		 * @return ICWP_WPSF_DataProcessor
		 */
		public function loadDataProcessor() {
			require_once( dirname(__FILE__).ICWP_DS.'icwp-data.php' );
			return ICWP_WPSF_DataProcessor::GetInstance();
		}
	}
endif;

if ( !class_exists( 'ICWP_WPSF_WpAdminNotices', false ) ):

	class ICWP_WPSF_WpAdminNotices extends ICWP_WPSF_WpAdminNotices_V1 {
		/**
		 * @return ICWP_WPSF_WpAdminNotices
		 */
		public static function GetInstance() {
			if ( is_null( self::$oInstance ) ) {
				self::$oInstance = new self();
			}
			return self::$oInstance;
		}
	}
endif;