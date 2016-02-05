<?php

if ( !class_exists('ICWP_WPSF_Processor_AuditTrail_Wordpress') ):

	require_once( dirname(__FILE__).ICWP_DS.'base.php' );

	class ICWP_WPSF_Processor_AuditTrail_Wordpress extends ICWP_WPSF_Processor_BaseWpsf {

		/**
		 */
		public function run() {
			if ( $this->getIsOption( 'enable_audit_context_wordpress', 'Y' ) ) {
				add_action( '_core_updated_successfully', array( $this, 'auditCoreUpdated' ) );
				add_action( 'update_option_permalink_structure', array( $this, 'auditPermalinkStructure' ), 10, 2 );
			}
		}

		/**
		 * @param string $sNewCoreVersion
		 * @return bool
		 */
		public function auditCoreUpdated( $sNewCoreVersion ) {
			global $wp_version;

			$oAuditTrail = $this->getAuditTrailEntries();
			$oAuditTrail->add(
				'wordpress',
				'core_updated',
				1,
				sprintf( _wpsf__( 'WordPress Core was updated from "v%s" to "v%s".' ), $wp_version, $sNewCoreVersion )
			);
		}

		/**
		 * @param string $sOld
		 * @param string $sNew
		 * @return bool
		 */
		public function auditPermalinkStructure( $sOld, $sNew ) {
			$oAuditTrail = $this->getAuditTrailEntries();
			$oAuditTrail->add(
				'wordpress',
				'permalinks_structure',
				1,
				sprintf( _wpsf__( 'WordPress Permalinks Structure was updated from "%s" to "%s".' ), $sOld, $sNew )
			);
		}

		/**
		 * @return ICWP_WPSF_AuditTrail_Entries
		 */
		protected function getAuditTrailEntries() {
			return ICWP_WPSF_AuditTrail_Entries::GetInstance();
		}
	}

endif;