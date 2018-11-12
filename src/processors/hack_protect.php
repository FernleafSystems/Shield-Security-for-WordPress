<?php

if ( class_exists( 'ICWP_WPSF_Processor_HackProtect', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base_wpsf.php' );

class ICWP_WPSF_Processor_HackProtect extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 * Override to set what this processor does when it's "run"
	 */
	public function run() {

		$sPath = $this->loadRequest()->getPath();
		if ( !empty( $sPath ) && ( strpos( $sPath, '/wp-admin/admin-ajax.php' ) !== false ) ) {
			$this->revSliderPatch_LFI();
			$this->revSliderPatch_AFU();
		}
		// not probably necessary any longer since it's patched in the Core
		add_filter( 'pre_comment_content', array( $this, 'secXss64kb' ), 0, 1 );

		$this->runScanner();
	}

	/**
	 */
	protected function runPluginVulnerabilities() {
		require_once( dirname( __FILE__ ).'/hackprotect_pluginvulnerabilities.php' );
		$oPv = new ICWP_WPSF_Processor_HackProtect_PluginVulnerabilities( $this->getMod() );
		$oPv->run();
	}

	/**
	 */
	protected function runScanner() {
		$this->getSubProcessorScanner()->run();
	}

	/**
	 * @return ICWP_WPSF_Processor_HackProtect_Scanner
	 */
	public function getSubProcessorScanner() {
		$oProc = $this->getSubPro( 'scanner' );
		if ( is_null( $oProc ) ) {
			require_once( dirname( __FILE__ ).'/hackprotect_scanner.php' );
			$oProc = new ICWP_WPSF_Processor_HackProtect_Scanner( $this->getMod() );
			$this->aSubPros[ 'scanner' ] = $oProc;
		}
		return $oProc;
	}

	/**
	 * Addresses this vulnerability: http://klikki.fi/adv/wordpress2.html
	 * @param string $sCommentContent
	 * @return string
	 */
	public function secXss64kb( $sCommentContent ) {
		// Comments shouldn't be any longer than 64KB
		if ( strlen( $sCommentContent ) >= ( 64*1024 ) ) {
			$sCommentContent = sprintf( _wpsf__( '%s escaped HTML the following comment due to its size: %s' ), $this->getController()
																													 ->getHumanName(), esc_html( $sCommentContent ) );
		}
		return $sCommentContent;
	}

	protected function revSliderPatch_LFI() {
		$oReq = $this->loadRequest();

		$sAction = $oReq->query( 'action', '' );
		$sFileExt = strtolower( $this->loadDP()->getExtension( $oReq->query( 'img', '' ) ) );
		if ( $sAction == 'revslider_show_image' && !empty( $sFileExt ) ) {
			if ( !in_array( $sFileExt, array( 'jpg', 'jpeg', 'png', 'tiff', 'tif', 'gif' ) ) ) {
				die( 'RevSlider Local File Inclusion Attempt' );
			}
		}
	}

	protected function revSliderPatch_AFU() {
		$oReq = $this->loadRequest();

		$sAction = strtolower( $oReq->request( 'action', '' ) );
		$sClientAction = strtolower( $oReq->request( 'client_action', '' ) );
		if ( ( strpos( $sAction, 'revslider_ajax_action' ) !== false || strpos( $sAction, 'showbiz_ajax_action' ) !== false ) && $sClientAction == 'update_plugin' ) {
			die( 'RevSlider Arbitrary File Upload Attempt' );
		}
	}
}