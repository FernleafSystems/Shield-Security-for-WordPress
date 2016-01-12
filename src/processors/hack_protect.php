<?php

if ( !class_exists( 'ICWP_WPSF_Processor_HackProtect', false ) ):

	require_once( dirname(__FILE__).ICWP_DS.'base.php' );

	class ICWP_WPSF_Processor_HackProtect extends ICWP_WPSF_Processor_Base {
		/**
		 * Override to set what this processor does when it's "run"
		 */
		public function run() {
			$oDp = $this->loadDataProcessor();

			$sPath = $oDp->getRequestPath();
			if ( !empty( $sPath ) && ( strpos( $sPath, '/wp-admin/admin-ajax.php' ) !== false ) ) {
				$this->revSliderPatch_LFI();
				$this->revSliderPatch_AFU();
			}

			// not probably necessary any longer since it's patched in the Core
			add_filter( 'pre_comment_content', array( $this, 'secXss64kb' ), 0, 1 );

			$this->runPluginVulnerabilities();
			$this->runChecksumScan();
		}

		/**
		 */
		protected function runPluginVulnerabilities() {
			require_once( dirname(__FILE__).ICWP_DS.'hackprotect_pluginvulnerabilities.php' );
			$oPv = new ICWP_WPSF_Processor_HackProtect_PluginVulnerabilities( $this->getFeatureOptions() );
			$oPv->run();
		}

		/**
		 */
		protected function runChecksumScan() {
			require_once( dirname(__FILE__).ICWP_DS.'hackprotect_corechecksumscan.php' );
			$oPv = new ICWP_WPSF_Processor_HackProtect_CoreChecksumScan( $this->getFeatureOptions() );
			$oPv->run();
		}

		/**
		 * Addresses this vulnerability: http://klikki.fi/adv/wordpress2.html
		 *
		 * @param string $sCommentContent
		 * @return string
		 */
		public function secXss64kb( $sCommentContent ) {
			// Comments shouldn't be any longer than 64KB
			if ( strlen( $sCommentContent ) >= ( 64 * 1024 ) ) {
				$sCommentContent = sprintf( _wpsf__( '%s escaped HTML the following comment due to its size: %s' ), $this->getController()->getHumanName(), esc_html( $sCommentContent ) );
			}
			return $sCommentContent;
		}

		protected function revSliderPatch_LFI() {
			$oDp = $this->loadDataProcessor();

			$sAction = $oDp->FetchGet( 'action', '' );
			$sFileExt = strtolower( $oDp->getExtension( $oDp->FetchGet( 'img', '' ) ) ) ;
			if ( $sAction == 'revslider_show_image' && !empty( $sFileExt ) ) {
				if ( !in_array( $sFileExt, array( 'jpg', 'jpeg', 'png', 'tiff', 'tif', 'gif' ) ) ) {
					die( 'RevSlider Local File Inclusion Attempt' );
				}
			}
		}

		protected function revSliderPatch_AFU() {
			$oDp = $this->loadDataProcessor();

			$sAction = strtolower( $oDp->FetchRequest( 'action', '' ) );
			$sClientAction = strtolower( $oDp->FetchRequest( 'client_action', '' ) );
			if ( ( strpos( $sAction, 'revslider_ajax_action' ) !== false || strpos( $sAction, 'showbiz_ajax_action' ) !== false ) && $sClientAction == 'update_plugin' ) {
				die( 'RevSlider Arbitrary File Upload Attempt' );
			}
		}
	}

endif;