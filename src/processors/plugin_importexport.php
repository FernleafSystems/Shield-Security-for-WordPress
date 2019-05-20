<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_Plugin_ImportExport extends ICWP_WPSF_Processor_BaseWpsf {

	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getMod();

		add_action( $this->prefix( 'importexport_notify' ), [ $this, 'runWhitelistNotify' ] );

		if ( $oFO->hasImportExportMasterImportUrl() ) {
			// For auto update whitelist notifications:
			add_action( $oFO->prefix( 'importexport_updatenotified' ), [ $this, 'runImport' ] );
		}
	}

	/**
	 * @return array
	 */
	public function buildInsightsVars() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		$aData = [
			'vars'  => [
				'form_nonce'  => $oMod->getNonceActionData( 'import_file_upload' ),
				'form_action' => $oMod->getUrl_AdminPage()
			],
			'ajax'  => [
				'import_from_site' => $oMod->getAjaxActionData( 'import_from_site', true ),
			],
			'flags' => [
				'can_importexport' => $this->getCon()->isPremiumActive(),
			],
			'hrefs' => [
				'export_file_download' => $this->createExportFileDownloadLink()
			]
		];

		return $aData;
	}

	/**
	 * @return string
	 */
	private function createExportFileDownloadLink() {
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getMod();
		$aActionNonce = $oFO->getNonceActionData( 'export_file_download' );
		return add_query_arg( $aActionNonce, $oFO->getUrl_AdminPage() );
	}

	public function runWhitelistNotify() {
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getMod();
		$oHttpReq = Services::HttpRequest();

		if ( $oFO->hasImportExportWhitelistSites() ) {

			$aQuery = [
				'blocking' => false,
				'body'     => [ 'shield_action' => 'importexport_updatenotified' ]
			];
			foreach ( $oFO->getImportExportWhitelist() as $sUrl ) {
				$oHttpReq->get( $sUrl, $aQuery );
			}

			$this->addToAuditEntry(
				__( 'Sent notifications to whitelisted sites for required options import.', 'wp-simple-firewall' ),
				1,
				'options_import_notify'
			);
		}
	}

	public function runAction() {

		try {
			$oReq = Services::Request();
			switch ( $oReq->query( 'shield_action' ) ) {

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
		Services::Data()->downloadStringAsFile(
			implode( "\n", $aData ),
			sprintf( 'shieldexport-%s-%s.json',
				Services::WpGeneral()->getHomeUrl( '', true ),
				$sFilename = date( 'Ymd_His' )
			)
		);
	}

	/**
	 * @throws \Exception
	 */
	public function importFromUploadFile() {
		if ( !$this->getCon()->isPluginAdmin() ) {
			throw new \Exception( 'Not currently logged-in as admin' );
		}

		if ( Services::Request()->post( 'confirm' ) != 'Y' ) {
			throw new \Exception( __( 'Please check the box to confirm your intent to overwrite settings', 'wp-simple-firewall' ) );
		};

		$oFs = Services::WpFs();
		if ( empty( $_FILES ) || !isset( $_FILES[ 'import_file' ] )
			 || empty( $_FILES[ 'import_file' ][ 'tmp_name' ] ) ) {
			throw new \Exception( 'Please select a file to upload' );
		}
		if ( $_FILES[ 'import_file' ][ 'size' ] == 0
			 || isset( $_FILES[ 'error' ] ) && $_FILES[ 'error' ] != UPLOAD_ERR_OK
			 || !$oFs->isFile( $_FILES[ 'import_file' ][ 'tmp_name' ] )
			 || filesize( $_FILES[ 'import_file' ][ 'tmp_name' ] ) === 0
		) {
			throw new \Exception( 'Uploading of file failed' );
		}

		$sContent = Services::WpFs()->getFileContent( $_FILES[ 'import_file' ][ 'tmp_name' ] );
		if ( empty( $sContent ) ) {
			throw new \Exception( 'File uploaded was empty' );
		}

		{//filter any comment lines
			$aParts = array_filter(
				array_map( 'trim', explode( "\n", $sContent ) ),
				function ( $sLine ) {
					return ( strpos( $sLine, '{' ) === 0 );
				}
			);
			if ( empty( $aParts ) ) {
				throw new \Exception( 'Options JSON could not be found in uploaded content.' );
			}
		}
		{//parse the options json
			$aData = @json_decode( array_shift( $aParts ), true );
			if ( empty( $aData ) || !is_array( $aData ) ) {
				throw new \Exception( 'Uploaded options data was not of the correct format' );
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
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getMod();

		if ( !$oFO->isPremium() ) {
			throw new \Exception(
				sprintf( __( 'Not currently running %s Pro.', 'wp-simple-firewall' ), $this->getCon()->getHumanName() ),
				1
			);
		}
		if ( !$oFO->isImportExportPermitted() ) {
			throw new \Exception( __( 'Export of options is currently disabled.', 'wp-simple-firewall' ), 2 );
		}
	}

	/**
	 * This is called from a remote site when this site sends out an export request to another
	 * site but without a secret key i.e. it assumes it's on the white list. We give a 30 second
	 * window for the handshake to complete.  We do not explicitly fail.
	 */
	public function runOptionsExportHandshake() {
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getMod();
		if ( $oFO->isPremium() && $oFO->isImportExportPermitted() &&
			 ( Services::Request()->ts() < $oFO->getImportExportHandshakeExpiresAt() ) ) {
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
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getMod();
		$oReq = Services::Request();

		$sCronHook = $oFO->prefix( 'importexport_updatenotified' );
		if ( wp_next_scheduled( $sCronHook ) ) {
			wp_clear_scheduled_hook( $sCronHook );
		}

		if ( !wp_next_scheduled( $sCronHook ) ) {

			wp_schedule_single_event( $oReq->ts() + 12, $sCronHook );

			preg_match( '#.*WordPress/.*\s+(.*)\s?#', $oReq->server( 'HTTP_USER_AGENT' ), $aMatches );
			if ( !empty( $aMatches[ 1 ] ) && filter_var( $aMatches[ 1 ], FILTER_VALIDATE_URL ) ) {
				$sUrl = parse_url( $aMatches[ 1 ], PHP_URL_HOST );
				if ( !empty( $sUrl ) ) {
					$sUrl = 'Site: '.$sUrl;
				}
			}
			else {
				$sUrl = '';
			}

			$this->addToAuditEntry(
				__( 'Received notification that options import required.', 'wp-simple-firewall' )
				.' '.sprintf( __( 'Current master site: %s', 'wp-simple-firewall' ), $oFO->getImportExportMasterImportUrl() ),
				1,
				'options_import_notified',
				$sUrl
			);
		}
	}

	/**
	 */
	private function exportToJsonResponse() {
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

		if ( !$oFO->isPremium() ) {
			$nCode = 1;
			$sMessage = sprintf( __( 'Not currently running %s Pro.', 'wp-simple-firewall' ), $this->getCon()
																								   ->getHumanName() );
		}
		else if ( !$oFO->isImportExportPermitted() ) {
			$nCode = 2;
			$sMessage = __( 'Export of options is currently disabled.', 'wp-simple-firewall' );
		}
		else if ( !$this->verifyUrlWithHandshake( $sUrl ) ) {
			$nCode = 3;
			$sMessage = __( 'Handshake verification failed.', 'wp-simple-firewall' );
		}
		else {
			$nCode = 0;
			$bSuccess = true;
			$aData = $this->getExportData();
			$sMessage = 'Options Exported Successfully';

			$this->addToAuditEntry(
				sprintf( __( 'Options exported to site %s.', 'wp-simple-firewall' ), $sUrl ), 1, 'options_exported'
			);

			if ( $bDoNetwork ) {
				if ( $sNetworkOpt === 'Y' ) {
					$oFO->addUrlToImportExportWhitelistUrls( $sUrl );
					$this->addToAuditEntry(
						sprintf( __( 'Site added to export white list: %s.', 'wp-simple-firewall' ), $sUrl ),
						1,
						'export_whitelist_site_added'
					);
				}
				else {
					$oFO->removeUrlFromImportExportWhitelistUrls( $sUrl );
					$this->addToAuditEntry(
						sprintf( __( 'Site removed from export white list: %s.', 'wp-simple-firewall' ), $sUrl ),
						1,
						'export_whitelist_site_removed'
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
	 * @param string    $sMasterSiteUrl
	 * @param string    $sSecretKey
	 * @param bool|null $bEnableNetwork
	 * @param string    $sSiteResponse
	 * @return int
	 */
	public function runImport( $sMasterSiteUrl = '', $sSecretKey = '', $bEnableNetwork = null, &$sSiteResponse = '' ) {
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getMod();
		$oDP = Services::Data();

		if ( empty( $sMasterSiteUrl ) ) {
			$sMasterSiteUrl = $oFO->getImportExportMasterImportUrl();
		}

		$aParts = parse_url( $sMasterSiteUrl );

		$sOriginalMasterSiteUrl = $oFO->getImportExportMasterImportUrl();
		$bHadMasterSiteUrl = $oFO->hasImportExportMasterImportUrl();
		$bCheckKeyFormat = !$oFO->hasImportExportMasterImportUrl();
		$sSecretKey = preg_replace( '#[^0-9a-z]#i', '', $sSecretKey );

		if ( $bCheckKeyFormat && empty( $sSecretKey ) ) {
			$nErrorCode = 1;
		}
		else if ( $bCheckKeyFormat && strlen( $sSecretKey ) != 40 ) {
			$nErrorCode = 2;
		}
		else if ( $bCheckKeyFormat && preg_match( '#[^0-9a-z]#i', $sSecretKey ) ) {
			$nErrorCode = 3; //unused
		}
		else if ( empty( $aParts ) ) {
			$nErrorCode = 4;
		}
		else if ( $oDP->validateSimpleHttpUrl( $sMasterSiteUrl ) === false ) {
			$nErrorCode = 4; // a final check
		}
		else {
			$bReady = true;
			$aEssential = [ 'scheme', 'host' ];
			foreach ( $aEssential as $sKey ) {
				$bReady = $bReady && !empty( $aParts[ $sKey ] );
			}

			$sMasterSiteUrl = $oDP->validateSimpleHttpUrl( $sMasterSiteUrl ); // final clean

			if ( !$bReady || !$sMasterSiteUrl ) {
				$nErrorCode = 4;
			}
			else {
				$oFO->startImportExportHandshake();

				$aData = [
					'shield_action' => 'importexport_export',
					'secret'        => $sSecretKey,
					'url'           => Services::WpGeneral()->getHomeUrl()
				];
				// Don't send the network setup request if it's the cron.
				if ( !is_null( $bEnableNetwork ) && !Services::WpGeneral()->isCron() ) {
					$aData[ 'network' ] = $bEnableNetwork ? 'Y' : 'N';
				}

				$sFinalUrl = add_query_arg( $aData, $sMasterSiteUrl );
				$sResponse = Services::HttpRequest()->getContent( $sFinalUrl );
				$aParts = @json_decode( $sResponse, true );

				if ( empty( $aParts ) ) {
					$nErrorCode = 5;
				}
				else if ( !isset( $aParts[ 'success' ] ) || !$aParts[ 'success' ] ) {

					if ( empty ( $aParts[ 'message' ] ) ) {
						$nErrorCode = 6;
					}
					else {
						$nErrorCode = 7;
						$sSiteResponse = $aParts[ 'message' ]; // This is crap because we can't use Response objects
					}
				}
				else if ( empty( $aParts[ 'data' ] ) || !is_array( $aParts[ 'data' ] ) ) {
					$nErrorCode = 8;
				}
				else {
					$this->processDataImport( $aParts[ 'data' ] );

					// Fix for the overwriting of the Master Site URL with an empty string.
					// Only do so if we're not turning it off. i.e on or no-change
					if ( is_null( $bEnableNetwork ) ) {
						if ( $bHadMasterSiteUrl && !$oFO->hasImportExportMasterImportUrl() ) {
							$oFO->setImportExportMasterImportUrl( $sOriginalMasterSiteUrl );
						}
					}
					else if ( $bEnableNetwork === true ) {
						$this->addToAuditEntry(
							sprintf( __( 'Master Site URL set to %s.', 'wp-simple-firewall' ), $sMasterSiteUrl ),
							1,
							'options_master_set'
						);
						$oFO->setImportExportMasterImportUrl( $sMasterSiteUrl );
					}
					else if ( $bEnableNetwork === false ) {
						$oFO->setImportExportMasterImportUrl( '' );
					}

					$nErrorCode = 0;
				}
			}
		}

		return $nErrorCode;
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
			$this->addToAuditEntry(
				sprintf( __( 'Options imported from %s.', 'wp-simple-firewall' ), $sImportSource ),
				1, 'options_imported'
			);
			$oFO->setImportExportLastImportHash( md5( serialize( $aImportData ) ) );
		}
		return $bImported;
	}

	/**
	 * Cron callback
	 */
	public function runDailyCron() {
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getMod();
		$this->runImport( $oFO->getImportExportMasterImportUrl() );
	}
}