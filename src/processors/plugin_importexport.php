<?php

if ( class_exists( 'ICWP_WPSF_Processor_Plugin_ImportExport', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'base_wpsf.php' );

class ICWP_WPSF_Processor_Plugin_ImportExport extends ICWP_WPSF_Processor_BaseWpsf {

	public function run() {
		add_action( 'init', array( $this, 'runOptionsExport' ) );
	}

	/**
	 */
	public function runOptionsExport() {
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getFeature();
		$oDP = $this->loadDP();

		$sSecretKey = $oDP->query( 'secret', '' );
		if ( !$oFO->isSecretKey( $sSecretKey ) ) {
			return; // we show no signs of responding to invalid secret keys
		}

		$bSuccess = false;
		$aData = array();

		if ( !$oFO->isPremium() ) {
			$nCode = 1;
			$sMessage = _wpsf__( 'Not currently running Shield Security Pro.' );
		}
		else if ( !$oFO->isOptionsImportExportPermitted() ) {
			$nCode = 2;
			$sMessage = _wpsf__( 'Export of options is currently disabled.' );
		}
		else {
			$nCode = 0;
			$bSuccess = true;
			$aData = apply_filters( $oFO->prefix( 'gather_options_for_export' ), array() );
			$sMessage = 'Options Exported Successfully';
		}

		$aResponse = array(
			'success' => $bSuccess,
			'code'    => $nCode,
			'message' => $sMessage,
			'data'    => $aData,
		);
		echo json_encode( $aResponse );
		die();
	}
}