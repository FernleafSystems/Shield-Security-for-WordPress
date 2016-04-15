<?php
if ( !class_exists( 'ICWP_WPSF_WpTrack', false ) ):

	class ICWP_WPSF_WpTrack extends ICWP_WPSF_Foundation {

		/**
		 * @var ICWP_WPSF_WpTrack
		 */
		protected static $oInstance = NULL;

		/**
		 * @var array
		 */
		protected $aFiredWpActions = array();

		private function __construct() {}

		/**
		 * @return ICWP_WPSF_WpTrack
		 */
		public static function GetInstance() {
			if ( is_null( self::$oInstance ) ) {
				self::$oInstance = new self();
			}
			return self::$oInstance;
		}
	}

endif;