<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_Plugin_ImportExport extends ICWP_WPSF_Processor_BaseWpsf {

	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oMod */
		$oMod = $this->getMod();

		add_action( $oMod->prefix( 'importexport_notify' ), [ $this, 'runWhitelistNotify' ] );

		if ( $oMod->hasImportExportMasterImportUrl() ) {
			// For auto update whitelist notifications:
			add_action( $oMod->prefix( 'importexport_updatenotified' ), [ $this, 'runImport' ] );
		}
	}

	/**
	 * @return array
	 */
	public function buildInsightsVars() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		$aData = [
			'vars'    => [
				'file_upload_nonce' => $oMod->getNonceActionData( 'import_file_upload' ),
				'form_action'       => $oMod->getUrl_AdminPage()
			],
			'ajax'    => [
				'import_from_site' => $oMod->getAjaxActionData( 'import_from_site', true ),
			],
			'flags'   => [
				'can_importexport' => $this->getCon()->isPremiumActive(),
			],
			'hrefs'   => [
				'export_file_download' => $this->createExportFileDownloadLink()
			],
			'strings' => [
				'title_import_file'    => __( 'Import From File', 'wp-simple-firewall' ),
				'subtitle_import_file' => __( 'Upload an exported options file you downloaded from another site', 'wp-simple-firewall' ),
				'select_import_file'   => __( 'Select file to import options from', 'wp-simple-firewall' ),
				'i_understand'         => __( 'I Understand Existing Options Will Be Overwritten', 'wp-simple-firewall' ),
				'be_sure'              => __( 'Please be sure that this is what you want.', 'wp-simple-firewall' ),
				'not_undone'           => __( "This action can't be undone.", 'wp-simple-firewall' ),
				'title_import_site'    => __( "Import From Site", 'wp-simple-firewall' ),

				'title_download_file'    => __( 'Download Options Export File', 'wp-simple-firewall' ),
				'subtitle_download_file' => __( 'Use this file to copy options from this site into another site', 'wp-simple-firewall' ),

				'subtitle_import_site'     => __( 'Import options directly from another site', 'wp-simple-firewall' ),
				'master_site_url'          => __( 'Master Site URL', 'wp-simple-firewall' ),
				'remember_include'         => sprintf(
					__( 'Remember to include %s or %s', 'wp-simple-firewall' ),
					'<code>https://</code>',
					'<code>http://</code>'
				),
				'secret_key'               => __( 'Secret Key', 'wp-simple-firewall' ),
				'master_site_key'          => __( 'Master Site Secret Key', 'wp-simple-firewall' ),
				'create_network'           => __( 'Create Shield Network', 'wp-simple-firewall' ),
				'key_found_under'          => sprintf( __( 'The secret key is found in: %s', 'wp-simple-firewall' ),
					ucwords( sprintf( '%s > %s > %s ', __( 'General Settings', 'wp-simple-firewall' ), __( 'Import/Export', 'wp-simple-firewall' ), __( 'Secret Key', 'wp-simple-firewall' ) ) )
				),
				'turn_on'                  => __( 'Turn On', 'wp-simple-firewall' ),
				'turn_off'                 => __( 'Turn Off', 'wp-simple-firewall' ),
				'no_change'                => __( 'No Change', 'wp-simple-firewall' ),
				'network_explain'          => [
					__( 'Checking this option on will link this site to Master site.', 'wp-simple-firewall' ),
					__( 'Options will be automatically imported from the Master site each night', 'wp-simple-firewall' ),
					__( 'When you adjust options on the Master site, they will be reflected in this site after the automatic import', 'wp-simple-firewall' ),
				],
				'import_options'           => __( 'Import Options', 'wp-simple-firewall' ),
				'downloading_please_wait'  => __( 'Downloading file, please wait...', 'wp-simple-firewall' ),
				'problem_downloading_file' => __( 'There was a problem downloading the file.', 'wp-simple-firewall' ),
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

			$this->getCon()->fireEvent( 'import_notify_sent' );
		}
	}

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
			throw new \Exception( __( 'Not currently logged-in as security admin', 'wp-simple-firewall' ) );
		}

		if ( Services::Request()->post( 'confirm' ) != 'Y' ) {
			throw new \Exception( __( 'Please check the box to confirm your intent to overwrite settings', 'wp-simple-firewall' ) );
		};

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

			$this->getCon()->fireEvent(
				'import_notify_received',
				[ 'audit' => [ 'master_site' => $oFO->getImportExportMasterImportUrl() ] ]
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
					$this->processDataImport( $aParts[ 'data' ], $sMasterSiteUrl );

					// Fix for the overwriting of the Master Site URL with an empty string.
					// Only do so if we're not turning it off. i.e on or no-change
					if ( is_null( $bEnableNetwork ) ) {
						if ( $bHadMasterSiteUrl && !$oFO->hasImportExportMasterImportUrl() ) {
							$oFO->setImportExportMasterImportUrl( $sOriginalMasterSiteUrl );
						}
					}
					else if ( $bEnableNetwork === true ) {
						$oFO->setImportExportMasterImportUrl( $sMasterSiteUrl );
						$this->getCon()->fireEvent(
							'master_url_set',
							[ 'audit' => [ 'site' => $sMasterSiteUrl ] ]
						);
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
			$oFO->setImportExportLastImportHash( md5( serialize( $aImportData ) ) );
			$this->getCon()->fireEvent(
				'options_imported',
				[ 'audit' => [ 'site' => $sImportSource ] ]
			);
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