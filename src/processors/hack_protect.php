<?php

if ( class_exists( 'ICWP_WPSF_Processor_HackProtect', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'base_wpsf.php' );

class ICWP_WPSF_Processor_HackProtect extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 * Override to set what this processor does when it's "run"
	 */
	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getFeature();

		$sPath = $this->loadDataProcessor()->getRequestPath();
		if ( !empty( $sPath ) && ( strpos( $sPath, '/wp-admin/admin-ajax.php' ) !== false ) ) {
			$this->revSliderPatch_LFI();
			$this->revSliderPatch_AFU();
		}
		// not probably necessary any longer since it's patched in the Core
		add_filter( 'pre_comment_content', array( $this, 'secXss64kb' ), 0, 1 );

		if ( $oFO->isWcfScanEnabled() ) {
			$this->runChecksumScan();
		}
		if ( $oFO->isUfsEnabled() ) {
			$this->runFileCleanerScan();
		}
		if ( $oFO->isWpvulnEnabled() ) {
			$this->runWpVulnScan();
		}
	}

	/**
	 */
	protected function runPluginVulnerabilities() {
		require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'hackprotect_pluginvulnerabilities.php' );
		$oPv = new ICWP_WPSF_Processor_HackProtect_PluginVulnerabilities( $this->getFeature() );
		$oPv->run();
	}

	/**
	 */
	protected function runChecksumScan() {
		$this->getSubProcessorChecksumScan()
			 ->run();
	}

	/**
	 * @param bool $bAutoRepair
	 * @return array
	 */
	public function runChecksumScanManual( $bAutoRepair ) {
		return $this->getSubProcessorChecksumScan()
					->doChecksumScan( $bAutoRepair );
	}

	/**
	 */
	protected function runFileCleanerScan() {
		$this->getSubProcessorFileCleanerScan()
			 ->run();
	}

	/**
	 */
	protected function runWpVulnScan() {
		$this->getSubProcessorWpVulnScan()
			 ->run();
	}

	/**
	 * @return ICWP_WPSF_Processor_HackProtect_CoreChecksumScan
	 */
	public function getSubProcessorChecksumScan() {
		$oProc = $this->getSubProcessor( 'checksum' );
		if ( is_null( $oProc ) ) {
			require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'hackprotect_corechecksumscan.php' );
			$oProc = new ICWP_WPSF_Processor_HackProtect_CoreChecksumScan( $this->getFeature() );
			$this->aSubProcessors[ 'checksum' ] = $oProc;
		}
		return $oProc;
	}

	/**
	 * @return ICWP_WPSF_Processor_HackProtect_FileCleanerScan
	 */
	public function getSubProcessorFileCleanerScan() {
		$oProc = $this->getSubProcessor( 'cleaner' );
		if ( is_null( $oProc ) ) {
			require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'hackprotect_filecleanerscan.php' );
			$oProc = new ICWP_WPSF_Processor_HackProtect_FileCleanerScan( $this->getFeature() );
			$this->aSubProcessors[ 'cleaner' ] = $oProc;
		}
		return $oProc;
	}

	/**
	 * @return ICWP_WPSF_Processor_HackProtect_WpVulnScan
	 */
	protected function getSubProcessorWpVulnScan() {
		$oProc = $this->getSubProcessor( 'vuln' );
		if ( is_null( $oProc ) ) {
			require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'hackprotect_wpvulnscan.php' );
			$oProc = new ICWP_WPSF_Processor_HackProtect_WpVulnScan( $this->getFeature() );
			$this->aSubProcessors[ 'vuln' ] = $oProc;
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
		$oDp = $this->loadDataProcessor();

		$sAction = $oDp->FetchGet( 'action', '' );
		$sFileExt = strtolower( $oDp->getExtension( $oDp->FetchGet( 'img', '' ) ) );
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