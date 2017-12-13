<?php

if ( class_exists( 'ICWP_WPSF_Processor_Plugin_ImportExport', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'base_wpsf.php' );

class ICWP_WPSF_Processor_Plugin_ImportExport extends ICWP_WPSF_Processor_BaseWpsf {

	public function run() {
		$oDP = $this->loadDP();
		switch ( $oDP->query( 'shield_action' ) ) {

			case 'importexport_export':
				add_action( 'init', array( $this, 'runOptionsExport' ) );
				break;

			case 'importexport_handshake':
				add_action( 'init', array( $this, 'runOptionsExportHandshake' ) );
				break;

			case 'importexport_updatenotify':
				add_action( 'init', array( $this, 'runOptionsUpdateNotify' ) );
				break;

			default:
				break;
		}
	}

	/**
	 * This is called from a remote site when this site sends out an export request to another
	 * site but without a secret key i.e. it assumes it's on the white list. We give a 30 second
	 * window for the handshake to complete.  We do not explicitly fail.
	 */
	public function runOptionsExportHandshake() {
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getFeature();
		if ( $oFO->isPremium() && $oFO->isImportExportPermitted() &&
			 ( $this->loadDP()->time() < $oFO->getImportExportHandshakeExpiresAt() ) ) {
			echo json_encode( array( 'success' => true ) );
			die();
		}
		else {
			return;
		}
	}

	/**
	 * TODO: set a cron to run in a minute to push out notifications to whitelisted sites.
	 */
	public function runOptionsUpdateNotify() {
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getFeature();
	}

	/**
	 */
	public function runOptionsExport() {
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getFeature();
		$oDP = $this->loadDP();

		$sSecretKey = trim( $oDP->query( 'secret', '' ) );
		$sUrl = $oDP->validateSimpleHttpUrl( $oDP->query( 'url', '' ) );
		if ( !$oFO->isImportExportSecretKey( $sSecretKey ) && !$this->isUrlOnWhitelist( $sUrl ) ) {
			return; // we show no signs of responding to invalid secret keys or unwhitelisted URLs
		}

		$bSuccess = false;
		$aData = array();

		if ( !$oFO->isPremium() ) {
			$nCode = 1;
			$sMessage = _wpsf__( 'Not currently running Shield Security Pro.' );
		}
		else if ( !$oFO->isImportExportPermitted() ) {
			$nCode = 2;
			$sMessage = _wpsf__( 'Export of options is currently disabled.' );
		}
		else if ( !$this->verifyUrlWithHandshake( $sUrl ) ) {
			$nCode = 3;
			$sMessage = _wpsf__( 'Handshake verification failed.' );
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

	/**
	 * @param string $sUrl
	 * @return bool
	 */
	protected function isUrlOnWhitelist( $sUrl ) {
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getFeature();
		return !empty( $sUrl ) && in_array( $sUrl, $oFO->getImportExportWhitelist() );
	}

	/**
	 * @param string $sUrl
	 * @return bool
	 */
	protected function verifyUrlWithHandshake( $sUrl ) {
		$bVerified = false;

		if ( !empty( $sUrl ) ) {
			$sFinalUrl = add_query_arg(
				array( 'shield_action' => 'importexport_handshake' ),
				$sUrl
			);
			$aParts = @json_decode( $this->loadFS()->getUrlContent( $sFinalUrl ), true );
			$bVerified = !empty( $aParts ) && is_array( $aParts )
						 && isset( $aParts[ 'success' ] ) && ( $aParts[ 'success' ] === true );
		}

		return $bVerified;
	}
}