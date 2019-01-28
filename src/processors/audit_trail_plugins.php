<?php

class ICWP_WPSF_Processor_AuditTrail_Plugins extends ICWP_WPSF_AuditTrail_Auditor_Base {

	/**
	 */
	public function run() {
		add_action( 'deactivated_plugin', array( $this, 'auditDeactivatedPlugin' ) );
		add_action( 'activated_plugin', array( $this, 'auditActivatedPlugin' ) );
		add_action( 'check_admin_referer', array( $this, 'auditEditedPluginFile' ), 10, 2 );
	}

	/**
	 * @param string $sPlugin
	 */
	public function auditActivatedPlugin( $sPlugin ) {
		if ( empty( $sPlugin ) ) {
			return;
		}

		$this->add( 'plugins', 'plugin_activated', 1,
			sprintf( _wpsf__( 'Plugin "%s" was activated.' ), $sPlugin )
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
			sprintf( _wpsf__( 'Plugin "%s" was deactivated.' ), $sPlugin )
		);
	}

	/**
	 * @param string  $sAction
	 * @param boolean $bResult
	 */
	public function auditEditedPluginFile( $sAction, $bResult ) {

		$sStub = 'edit-plugin_';
		if ( strpos( $sAction, $sStub ) !== 0 ) {
			return;
		}

		$sFileName = str_replace( $sStub, '', $sAction );

		$this->add( 'plugins', 'file_edited', 2,
			sprintf( _wpsf__( 'An attempt was made to edit the plugin file "%s" directly through the WordPress editor.' ), $sFileName )
		);
	}
}