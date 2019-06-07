<?php

use FernleafSystems\Wordpress\Services\Services;

/**
 * Class ICWP_WPSF_Processor_AuditTrail_Wordpress
 * @deprecated
 */
class ICWP_WPSF_Processor_AuditTrail_Wordpress extends ICWP_WPSF_AuditTrail_Auditor_Base {

	/**
	 */
	public function run() {
		add_action( '_core_updated_successfully', [ $this, 'auditCoreUpdated' ] );
		add_action( 'update_option_permalink_structure', [ $this, 'auditPermalinkStructure' ], 10, 2 );
	}

	/**
	 * @param string $sNewCoreVersion
	 */
	public function auditCoreUpdated( $sNewCoreVersion ) {
		$this->add( 'wordpress', 'core_updated', 1,
			sprintf( __( 'WordPress Core was updated from "v%s" to "v%s".', 'wp-simple-firewall' ),
				Services::WpGeneral()->getVersion(),
				$sNewCoreVersion
			)
		);
	}

	/**
	 * @param string $sOld
	 * @param string $sNew
	 */
	public function auditPermalinkStructure( $sOld, $sNew ) {
		$this->add( 'wordpress', 'permalinks_structure', 1,
			sprintf( __( 'WordPress Permalinks Structure was updated from "%s" to "%s".', 'wp-simple-firewall' ), $sOld, $sNew )
		);
	}
}