<?php

/**
 * Class ICWP_WPSF_Processor_AuditTrail_Themes
 * @deprecated
 */
class ICWP_WPSF_Processor_AuditTrail_Themes extends ICWP_WPSF_AuditTrail_Auditor_Base {

	/**
	 */
	public function run() {
		add_action( 'switch_theme', [ $this, 'auditSwitchTheme' ] );
		add_action( 'check_admin_referer', [ $this, 'auditEditedThemeFile' ], 10, 2 );
	}

	/**
	 * @param string $sThemeName
	 */
	public function auditSwitchTheme( $sThemeName ) {
		if ( !empty( $sThemeName ) ) {
			$this->add( 'themes', 'theme_activated', 1,
				sprintf( __( 'Theme "%s" was activated.', 'wp-simple-firewall' ), $sThemeName )
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
				sprintf( __( 'An attempt was made to edit the theme file "%s" directly through the WordPress editor.', 'wp-simple-firewall' ), $sFileName )
			);
		}
	}
}