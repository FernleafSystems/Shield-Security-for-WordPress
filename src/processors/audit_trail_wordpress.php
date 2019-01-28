<?php

class ICWP_WPSF_Processor_AuditTrail_Wordpress extends ICWP_WPSF_AuditTrail_Auditor_Base {

	/**
	 */
	public function run() {
		add_action( '_core_updated_successfully', array( $this, 'auditCoreUpdated' ) );
		add_action( 'update_option_permalink_structure', array( $this, 'auditPermalinkStructure' ), 10, 2 );
	}

	/**
	 * @param string $sNewCoreVersion
	 */
	public function auditCoreUpdated( $sNewCoreVersion ) {
		global $wp_version;
		$this->add( 'wordpress', 'core_updated', 1,
			sprintf( _wpsf__( 'WordPress Core was updated from "v%s" to "v%s".' ), $wp_version, $sNewCoreVersion )
		);
	}

	/**
	 * @param string $sOld
	 * @param string $sNew
	 */
	public function auditPermalinkStructure( $sOld, $sNew ) {
		$this->add( 'wordpress', 'permalinks_structure', 1,
			sprintf( _wpsf__( 'WordPress Permalinks Structure was updated from "%s" to "%s".' ), $sOld, $sNew )
		);
	}
}