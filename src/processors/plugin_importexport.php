<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_Plugin_ImportExport extends Shield\Modules\BaseShield\ShieldProcessor {

	public function runAction() {

		try {
			$oReq = Services::Request();
			switch ( $this->getCon()->getShieldAction() ) {

				case 'importexport_export':
					$this->executeExport( $oReq->query( 'method' ) );
					break;

				case 'importexport_import':
					$this->executeImport( $oReq->query( 'method' ) );
					break;

				case 'importexport_handshake':
					$this->runOptionsExportHandshake();
					break;

				case 'importexport_updatenotified':
					$this->runOptionsUpdateNotified();
					break;

				default:
					break;
			}
		}
		catch ( \Exception $oE ) {
		}
	}

	/**
	 * @param string $sMethod
	 */
	private function executeExport( $sMethod = 'json' ) {

		try {
			$this->preActionVerify();

			switch ( $sMethod ) {
				case 'file':
					$this->downloadExportToFile();
					break;

				case 'json':
				default:
					$this->exportToJsonResponse();
					break;
			}
		}
		catch ( \Exception $oE ) {
		}
		die();
	}

	/**
	 * @param string $sMethod
	 */
	private function executeImport( $sMethod = 'file' ) {

		try {
			$this->preActionVerify();

			switch ( $sMethod ) {
				case 'file':
				default:
					$this->importFromUploadFile();
					break;
			}
		}
		catch ( \Exception $oE ) {
		}
		die();
	}

	/**
	 * @throws \Exception
	 */
	private function downloadExportToFile() {
		if ( !$this->getCon()->isPluginAdmin() ) {
			throw new \Exception( 'Not currently logged-in as admin' );
		}
		$this->doExportDownload();
	}

	public function doExportDownload() {
		$sExport = json_encode( $this->getExportData() );
		$aData = [
			'# Site URL: '.Services::WpGeneral()->getHomeUrl(),
			'# Export Date: '.Services::WpGeneral()->getTimeStringForDisplay(),
			'# Hash: '.sha1( $sExport ),
			$sExport
		];
		Services::Response()->downloadStringAsFile(
			implode( "\n", $aData ),
			sprintf( 'shieldexport-%s-%s.json',
				Services::Data()->urlStripSchema( Services::WpGeneral()->getHomeUrl() ),
				$sFilename = date( 'Ymd_His' )
			)
		);
	}

	/**
	 * @throws \Exception
	 */
	public function importFromUploadFile() {
		if ( !$this->getCon()->isPluginAdmin() ) {
			throw new \Exception( __( 'Not currently logged-in as security admin', 'wp-simple-firewall' ) );
		}

		if ( Services::Request()->post( 'confirm' ) != 'Y' ) {
			throw new \Exception( __( 'Please check the box to confirm your intent to overwrite settings', 'wp-simple-firewall' ) );
		}

		$oFs = Services::WpFs();
		if ( empty( $_FILES ) || !isset( $_FILES[ 'import_file' ] )
			 || empty( $_FILES[ 'import_file' ][ 'tmp_name' ] ) ) {
			throw new \Exception( __( 'Please select a file to upload', 'wp-simple-firewall' ) );
		}
		if ( $_FILES[ 'import_file' ][ 'size' ] == 0
			 || isset( $_FILES[ 'error' ] ) && $_FILES[ 'error' ] != UPLOAD_ERR_OK
			 || !$oFs->isFile( $_FILES[ 'import_file' ][ 'tmp_name' ] )
			 || filesize( $_FILES[ 'import_file' ][ 'tmp_name' ] ) === 0
		) {
			throw new \Exception( __( 'Uploading of file failed', 'wp-simple-firewall' ) );
		}

		$sContent = Services::WpFs()->getFileContent( $_FILES[ 'import_file' ][ 'tmp_name' ] );
		if ( empty( $sContent ) ) {
			throw new \Exception( __( 'Uploaded file was empty', 'wp-simple-firewall' ) );
		}

		{//filter any comment lines
			$aParts = array_filter(
				array_map( 'trim', explode( "\n", $sContent ) ),
				function ( $sLine ) {
					return ( strpos( $sLine, '{' ) === 0 );
				}
			);
			if ( empty( $aParts ) ) {
				throw new \Exception( __( 'Options data could not be found in uploaded file', 'wp-simple-firewall' ) );
			}
		}
		{//parse the options json
			$aData = @json_decode( array_shift( $aParts ), true );
			if ( empty( $aData ) || !is_array( $aData ) ) {
				throw new \Exception( __( 'Uploaded options data was not of the correct format', 'wp-simple-firewall' ) );
			}
		}

		$this->processDataImport( $aData, __( 'uploaded file', 'wp-simple-firewall' ) );
		$oFs->deleteFile( $_FILES[ 'import_file' ][ 'tmp_name' ] );
	}

	/**
	 * @return array
	 */
	private function getExportData() {
		$aD = apply_filters( $this->getMod()->prefix( 'gather_options_for_export' ), [] );
		return is_array( $aD ) ? $aD : [];
	}

	/**
	 * @throws \Exception
	 */
	private function preActionVerify() {
		/** @var Plugin\Options $oOpts */
		$oOpts = $this->getOptions();
		$oCon = $this->getCon();

		if ( !$oCon->isPremiumActive() ) {
			throw new \Exception(
				sprintf( __( 'Not currently running %s Pro.', 'wp-simple-firewall' ), $oCon->getHumanName() ),
				1
			);
		}
		if ( !$oOpts->isImportExportPermitted() ) {
			throw new \Exception( __( 'Export of options is currently disabled.', 'wp-simple-firewall' ), 2 );
		}
	}

	/**
	 * This is called from a remote site when this site sends out an export request to another
	 * site but without a secret key i.e. it assumes it's on the white list. We give a 30 second
	 * window for the handshake to complete.  We do not explicitly fail.
	 */
	public function runOptionsExportHandshake() {
		/** @var Plugin\Options $oOpts */
		$oOpts = $this->getOptions();
		if ( $oOpts->isImportExportPermitted() &&
			 ( Services::Request()->ts() < $oOpts->getImportExportHandshakeExpiresAt() ) ) {
			echo json_encode( [ 'success' => true ] );
			die();
		}
		else {
			return;
		}
	}

	/**
	 * We've been notified that there's an update to pull in from the master site so we set a cron to do this.
	 */
	public function runOptionsUpdateNotified() {
		$oCon = $this->getCon();
		/** @var Plugin\Options $oOpts */
		$oOpts = $this->getOptions();

		$sCronHook = $oCon->prefix( 'importexport_updatenotified' );
		if ( wp_next_scheduled( $sCronHook ) ) {
			wp_clear_scheduled_hook( $sCronHook );
		}

		if ( !wp_next_scheduled( $sCronHook ) ) {

			wp_schedule_single_event( Services::Request()->ts() + 12, $sCronHook );

			preg_match( '#.*WordPress/.*\s+(.*)\s?#', Services::Request()->getUserAgent(), $aMatches );
			if ( !empty( $aMatches[ 1 ] ) && filter_var( $aMatches[ 1 ], FILTER_VALIDATE_URL ) ) {
				$sUrl = parse_url( $aMatches[ 1 ], PHP_URL_HOST );
				if ( !empty( $sUrl ) ) {
					$sUrl = 'Site: '.$sUrl;
				}
			}
			else {
				$sUrl = '';
			}

			$this->getCon()->fireEvent(
				'import_notify_received',
				[ 'audit' => [ 'master_site' => $oOpts->getImportExportMasterImportUrl() ] ]
			);
		}
	}

	private function exportToJsonResponse() {
		/** @var Plugin\Options $oOpts */
		$oOpts = $this->getOptions();
		$oCon = $this->getCon();
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getMod();
		$oReq = Services::Request();

		$sSecretKey = $oReq->query( 'secret', '' );

		$sNetworkOpt = $oReq->query( 'network', '' );
		$bDoNetwork = !empty( $sNetworkOpt );
		$sUrl = Services::Data()->validateSimpleHttpUrl( $oReq->query( 'url', '' ) );

		if ( !$oFO->isImportExportSecretKey( $sSecretKey ) && !$this->isUrlOnWhitelist( $sUrl ) ) {
			return; // we show no signs of responding to invalid secret keys or unwhitelisted URLs
		}

		$bSuccess = false;
		$aData = [];

		if ( !$oCon->isPremiumActive() ) {
			$nCode = 1;
			$sMessage = sprintf( __( 'Not currently running %s Pro.', 'wp-simple-firewall' ), $this->getCon()
																								   ->getHumanName() );
		}
		elseif ( !$oOpts->isImportExportPermitted() ) {
			$nCode = 2;
			$sMessage = __( 'Export of options is currently disabled.', 'wp-simple-firewall' );
		}
		elseif ( !$this->verifyUrlWithHandshake( $sUrl ) ) {
			$nCode = 3;
			$sMessage = __( 'Handshake verification failed.', 'wp-simple-firewall' );
		}
		else {
			$nCode = 0;
			$bSuccess = true;
			$aData = $this->getExportData();
			$sMessage = 'Options Exported Successfully';

			$this->getCon()->fireEvent(
				'options_exported',
				[ 'audit' => [ 'site' => $sUrl ] ]
			);

			if ( $bDoNetwork ) {
				if ( $sNetworkOpt === 'Y' ) {
					$oFO->addUrlToImportExportWhitelistUrls( $sUrl );
					$this->getCon()->fireEvent(
						'whitelist_site_added',
						[ 'audit' => [ 'site' => $sUrl ] ]
					);
				}
				else {
					$oFO->removeUrlFromImportExportWhitelistUrls( $sUrl );
					$this->getCon()->fireEvent(
						'whitelist_site_removed',
						[ 'audit' => [ 'site' => $sUrl ] ]
					);
				}
			}
		}

		$aResponse = [
			'success' => $bSuccess,
			'code'    => $nCode,
			'message' => $sMessage,
			'data'    => $aData,
		];
		echo json_encode( $aResponse );
		die();
	}

	/**
	 * @param string $sUrl
	 * @return bool
	 */
	protected function isUrlOnWhitelist( $sUrl ) {
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getMod();
		return !empty( $sUrl ) && in_array( $sUrl, $oFO->getImportExportWhitelist() );
	}

	/**
	 * @param string $sUrl
	 * @return bool
	 */
	private function verifyUrlWithHandshake( $sUrl ) {
		$bVerified = false;

		if ( !empty( $sUrl ) ) {
			$sReqUrl = add_query_arg( [ 'shield_action' => 'importexport_handshake' ], $sUrl );
			$aResp = @json_decode( Services::HttpRequest()->getContent( $sReqUrl ), true );
			$bVerified = is_array( $aResp ) && isset( $aResp[ 'success' ] ) && ( $aResp[ 'success' ] === true );
		}

		return $bVerified;
	}

	/**
	 * @param array  $aImportData
	 * @param string $sImportSource
	 * @return bool
	 */
	private function processDataImport( $aImportData, $sImportSource = 'unspecified' ) {
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getMod();
		$bImported = false;
		if ( md5( serialize( $aImportData ) ) != $oFO->getImportExportLastImportHash() ) {
			do_action( $oFO->prefix( 'import_options' ), $aImportData );
			$oFO->setImportExportLastImportHash( md5( serialize( $aImportData ) ) );
			$this->getCon()->fireEvent(
				'options_imported',
				[ 'audit' => [ 'site' => $sImportSource ] ]
			);
		}
		return $bImported;
	}
}