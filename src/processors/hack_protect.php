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
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();

		$sPath = $this->loadRequest()->getPath();
		if ( !empty( $sPath ) && ( strpos( $sPath, '/wp-admin/admin-ajax.php' ) !== false ) ) {
			$this->revSliderPatch_LFI();
			$this->revSliderPatch_AFU();
		}
		// not probably necessary any longer since it's patched in the Core
		add_filter( 'pre_comment_content', array( $this, 'secXss64kb' ), 0, 1 );

		$this->runScanner();

		if ( $oFO->isWcfScanEnabled() ) {
			$this->runChecksumScan();
		}
		if ( $oFO->isUfcEnabled() ) {
			$this->runFileCleanerScan();
		}
		if ( $oFO->isWpvulnEnabled() ) {
			$this->runWpVulnScan();
		}
		if ( $oFO->isIcEnabled() ) {
			$this->getSubProcessorIntegrity()->run();
		}
		if ( $oFO->isPtgEnabled() ) {
			$this->runPTGuard();
		}
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
	 */
	protected function runChecksumScan() {
		$this->getSubProcessorWcf()
			 ->run();
	}

	/**
	 */
	protected function runFileCleanerScan() {
		$this->getSubProcessorUfc()
			 ->run();
	}

	/**
	 */
	protected function runWpVulnScan() {
		$this->getSubProcessorWpVulnScan()
			 ->run();
	}

	/**
	 */
	protected function runPTGuard() {
		$this->getSubProcessorPtg()->run();
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
	 * @return ICWP_WPSF_Processor_HackProtect_Wcf
	 */
	public function getSubProcessorWcf() {
		$oProc = $this->getSubPro( 'wcf' );
		if ( is_null( $oProc ) ) {
			require_once( dirname( __FILE__ ).'/hackprotect_scan_wcf.php' );
			$oProc = ( new ICWP_WPSF_Processor_HackProtect_Wcf( $this->getMod() ) )
				->setScannerDb( $this->getSubProcessorScanner() );
			$this->aSubPros[ 'wcf' ] = $oProc;
		}
		return $oProc;
	}

	/**
	 * @return ICWP_WPSF_Processor_HackProtect_Ufc
	 */
	public function getSubProcessorUfc() {
		$oProc = $this->getSubPro( 'ufc' );
		if ( is_null( $oProc ) ) {
			require_once( dirname( __FILE__ ).'/hackprotect_scan_ufc.php' );
			$oProc = ( new ICWP_WPSF_Processor_HackProtect_Ufc( $this->getMod() ) )
				->setScannerDb( $this->getSubProcessorScanner() );
			$this->aSubPros[ 'ufc' ] = $oProc;
		}
		return $oProc;
	}

	/**
	 * @return ICWP_WPSF_Processor_HackProtect_Ptg
	 */
	public function getSubProcessorPtg() {
		$oProc = $this->getSubPro( 'ptg' );
		if ( is_null( $oProc ) ) {
			require_once( dirname( __FILE__ ).'/hackprotect_scan_ptg.php' );
			$oProc = ( new ICWP_WPSF_Processor_HackProtect_Ptg( $this->getMod() ) )
				->setScannerDb( $this->getSubProcessorScanner() );
			$this->aSubPros[ 'ptg' ] = $oProc;
		}
		return $oProc;
	}

	/**
	 * @return ICWP_WPSF_Processor_HackProtect_Integrity
	 */
	protected function getSubProcessorIntegrity() {
		require_once( dirname( __FILE__ ).'/hackprotect_integrity.php' );
		$oProc = new ICWP_WPSF_Processor_HackProtect_Integrity( $this->getMod() );
		return $oProc;
	}

	/**
	 * @return ICWP_WPSF_Processor_HackProtect_WpVulnScan
	 */
	protected function getSubProcessorWpVulnScan() {
		$oProc = $this->getSubPro( 'vuln' );
		if ( is_null( $oProc ) ) {
			require_once( dirname( __FILE__ ).'/hackprotect_wpvulnscan.php' );
			$oProc = ( new ICWP_WPSF_Processor_HackProtect_WpVulnScan( $this->getMod() ) );
//				->setScannerDb( $this->getSubProcessorScanner() );
			$this->aSubPros[ 'vuln' ] = $oProc;
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