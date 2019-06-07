<?php

/**
 * Class ICWP_WPSF_Processor_AuditTrail_Plugins
 * @deprecated
 */
class ICWP_WPSF_Processor_AuditTrail_Plugins extends ICWP_WPSF_AuditTrail_Auditor_Base {

	/**
	 */
	public function run() {
		add_action( 'deactivated_plugin', [ $this, 'auditDeactivatedPlugin' ] );
		add_action( 'activated_plugin', [ $this, 'auditActivatedPlugin' ] );
		add_action( 'check_admin_referer', [ $this, 'auditEditedPluginFile' ], 10, 2 );
	}

	/**
	 * @param string $sPlugin
	 */
	public function auditActivatedPlugin( $sPlugin ) {
		if ( empty( $sPlugin ) ) {
			return;
		}

		$this->add( 'plugins', 'plugin_activated', 1,
			sprintf( __( 'Plugin "%s" was activated.', 'wp-simple-firewall' ), $sPlugin )
		);
	}

	/**
	 * @param string $sPlugin
	 */
	public function auditDeactivatedPlugin( $sPlugin ) {
		if ( empty( $sPlugin ) ) {
			return;
		}

		$this->add( 'plugins', 'plugin_deactivated', 1,
			sprintf( __( 'Plugin "%s" was deactivated.', 'wp-simple-firewall' ), $sPlugin )
		);
	}

	/**
	 * @param string $sAction
	 * @param bool   $bResult
	 */
	public function auditEditedPluginFile( $sAction, $bResult ) {

		$sStub = 'edit-plugin_';
		if ( strpos( $sAction, $sStub ) !== 0 ) {
			return;
		}

		$sFileName = str_replace( $sStub, '', $sAction );

		$this->add( 'plugins', 'file_edited', 2,
			sprintf( __( 'An attempt was made to edit the plugin file "%s" directly through the WordPress editor.', 'wp-simple-firewall' ), $sFileName )
		);
	}
}