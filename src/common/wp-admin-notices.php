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
			$this->sActionPrefix = $sPrefix;
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