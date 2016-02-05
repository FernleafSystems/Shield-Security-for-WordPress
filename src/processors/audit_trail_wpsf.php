<?php

if ( !class_exists('ICWP_WPSF_Processor_AuditTrail_Wpsf') ):

	require_once( dirname(__FILE__).ICWP_DS.'base.php' );

	class ICWP_WPSF_Processor_AuditTrail_Wpsf extends ICWP_WPSF_Processor_BaseWpsf {

		/**
		 */
		public function run() { }

		/**
		 * @return ICWP_WPSF_AuditTrail_Entries
		 */
		protected function getAuditTrailEntries() {
			return ICWP_WPSF_AuditTrail_Entries::GetInstance();
		}
	}

endif;