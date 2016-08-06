<?php
if ( !class_exists( 'ICWP_WPSF_Factory', false ) ):

	class ICWP_WPSF_Factory {

		/**
		 * @param string $sOptionsName
		 * @return ICWP_WPSF_OptionsVO
		 */
		static public function OptionsVo( $sOptionsName ) {
			if ( !class_exists( 'ICWP_WPSF_OptionsVO' ) ) {
				require_once( dirname(__FILE__).DIRECTORY_SEPARATOR.'icwp-optionsvo.php' );
			}
			return new ICWP_WPSF_OptionsVO( $sOptionsName );
		}



		/**
		 * @var ICWP_WPSF_WpCron
		 */
		protected static $oInstance = NULL;

		private function __construct() {}
	}

endif;