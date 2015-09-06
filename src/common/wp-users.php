<?php
if ( !class_exists( 'ICWP_WPSF_WpUsers', false ) ):

	class ICWP_WPSF_WpUsers {

		/**
		 * @var ICWP_WPSF_WpUsers
		 */
		protected static $oInstance = NULL;

		private function __construct() {}

		/**
		 * @return ICWP_WPSF_WpUsers
		 */
		public static function GetInstance() {
			if ( is_null( self::$oInstance ) ) {
				self::$oInstance = new self();
			}
			return self::$oInstance;
		}

		/**
		 * @return null|WP_User
		 */
		public function getCurrentWpUser() {
			if ( is_user_logged_in() ) {
				$oUser = wp_get_current_user();
				if ( is_object( $oUser ) && $oUser instanceof WP_User ) {
					return $oUser;
				}
			}
			return null;
		}
	}

endif;