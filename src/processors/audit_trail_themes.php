<?php

class ICWP_WPSF_Processor_AuditTrail_Themes extends ICWP_WPSF_AuditTrail_Auditor_Base {

	/**
	 */
	public function run() {
		add_action( 'switch_theme', array( $this, 'auditSwitchTheme' ) );
		add_action( 'check_admin_referer', array( $this, 'auditEditedThemeFile' ), 10, 2 );
//		add_action( 'upgrader_process_complete', array( $this, 'auditInstalledTheme' ) );
	}

	/**
	 * @param string $sThemeName
	 */
	public function auditSwitchTheme( $sThemeName ) {
		if ( !empty( $sThemeName ) ) {
			$this->add( 'themes', 'theme_activated', 1,
				sprintf( _wpsf__( 'Theme "%s" was activated.' ), $sThemeName )
			);
		}
	}

	/**
	 * @param string  $sAction
	 * @param boolean $bResult
	 */
	public function auditEditedThemeFile( $sAction, $bResult ) {

		$sStub = 'edit-theme_';
		if ( strpos( $sAction, $sStub ) === 0 ) {

			$sFileName = str_replace( $sStub, '', $sAction );
			$this->add( 'themes', 'file_edited', 2,
				sprintf( _wpsf__( 'An attempt was made to edit the theme file "%s" directly through the WordPress editor.' ), $sFileName )
			);
		}
	}
}