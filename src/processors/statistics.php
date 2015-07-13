<?php

if ( !class_exists('ICWP_WPSF_Processor_Statistics_V1') ):

	require_once( dirname(__FILE__).ICWP_DS.'base.php' );

	class ICWP_WPSF_Processor_Statistics_V1 extends ICWP_WPSF_Processor_Base {

		/**
		 */
		public function run() {
		}
	}

endif;

if ( !class_exists('ICWP_WPSF_Processor_Statistics') ):
	class ICWP_WPSF_Processor_Statistics extends ICWP_WPSF_Processor_Statistics_V1 { }
endif;