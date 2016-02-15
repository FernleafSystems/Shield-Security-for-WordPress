<?php

if ( !class_exists('ICWP_WPSF_Processor_AuditTrail_Themes') ):

	require_once( dirname(__FILE__).ICWP_DS.'base_wpsf.php' );

	class ICWP_WPSF_Processor_AuditTrail_Themes extends ICWP_WPSF_Processor_BaseWpsf {

		/**
		 */
		public function run() {
			if ( $this->getIsOption( 'enable_audit_context_themes', 'Y' ) ) {
				add_action( 'switch_theme', array( $this, 'auditSwitchTheme' ) );
				add_action( 'check_admin_referer', array( $this, 'auditEditedThemeFile' ), 10, 2 );
//				add_action( 'upgrader_process_complete', array( $this, 'auditInstalledTheme' ) );
			}
		}

		/**
		 * @param string $sThemeName
		 */
		public function auditSwitchTheme( $sThemeName ) {
			if ( empty( $sThemeName ) ) {
				return;
			}

			$oAuditTrail = $this->getAuditTrailEntries();
			$oAuditTrail->add(
				'themes',
				'theme_activated',
				1,
				sprintf( _wpsf__( 'Theme "%s" was activated.' ), $sThemeName )
			);
		}

		/**
		 * @param string $sAction
		 * @param boolean $bResult
		 */
		public function auditEditedThemeFile( $sAction, $bResult ) {

			$sStub = 'edit-theme_';
			if ( strpos( $sAction, $sStub ) !== 0 ) {
				return;
			}

			$sFileName = str_replace( $sStub, '', $sAction );

			$oAuditTrail = $this->getAuditTrailEntries();
			$oAuditTrail->add(
				'themes',
				'file_edited',
				2,
				sprintf( _wpsf__( 'An attempt was made to edit the theme file "%s" directly through the WordPress editor.' ), $sFileName )
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