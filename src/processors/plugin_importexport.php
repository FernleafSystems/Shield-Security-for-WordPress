<?php

use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_Plugin_ImportExport extends ICWP_WPSF_Processor_BaseWpsf {

	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getMod();

		add_action( $this->prefix( 'importexport_notify' ), array( $this, 'runWhitelistNotify' ) );

		if ( $oFO->hasImportExportMasterImportUrl() ) {
			try {
				$this->setupCronImport();
			}
			catch ( \Exception $oE ) {
				error_log( $oE->getMessage() );
			}
		}
	}

	/**
	 * @return array
	 */
	public function buildInsightsVars() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		$aData = [
			'vars'  => array(
				'form_nonce'  => $oMod->getNonceActionData( 'import_file_upload' ),
				'form_action' => $oMod->getUrl_AdminPage()
			),
			'ajax'  => array(
				'import_from_site' => $oMod->getAjaxActionData( 'import_from_site', true ),
			),
			'hrefs' => array(
				'export_file_download' => $this->createExportFileDownloadLink()
			)
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

		if ( $oFO->hasImportExportWhitelistSites() ) {

			foreach ( $oFO->getImportExportWhitelist() as $sUrl ) {
				$this->loadFS()->getUrl(
					$sUrl,
					array(
						'blocking' => false,
						'body'     => array( 'shield_action' => 'importexport_updatenotified' )
					)
				);
			}

			$this->addToAuditEntry(
				_wpsf__( 'Sent notifications to whitelisted sites for required options import.' ),
				1,
				'options_import_notify'
			);
		}
	}

	public function runAction() {

		try {
			$oReq = $this->loadRequest();
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
		Services::Data()->downloadStringAsFile(
			json_encode( $this->getExportData() ),
			sprintf( 'shieldexport-%s-%s.json',
				Services::WpGeneral()->getHomeUrl( true ),
				$sFilename = date( 'YmdHis' )
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
			throw new \Exception( _wpsf__( 'Please check the box to confirm your intent to overwrite settings' ) );
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

		$aData = @json_decode( $sContent, true );
		if ( empty( $aData ) || !is_array( $aData ) ) {
			throw new \Exception( 'Uploaded file data was not of the correct format' );
		}

		$this->processDataImport( $aData, _wpsf__( 'uploaded file' ) );
		$oFs->deleteFile( $_FILES[ 'import_file' ][ 'tmp_name' ] );
	}

	/**
	 * @return array
	 */
	private function getExportData() {
		$aD = apply_filters( $this->getMod()->prefix( 'gather_options_for_export' ), array() );
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
				sprintf( _wpsf__( 'Not currently running %s Pro.' ), $this->getCon()->getHumanName() ),
				1
			);
		}
		if ( !$oFO->isImportExportPermitted() ) {
			throw new \Exception( _wpsf__( 'Export of options is currently disabled.' ), 2 );
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
			 ( $this->loadRequest()->ts() < $oFO->getImportExportHandshakeExpiresAt() ) ) {
			echo json_encode( array( 'success' => true ) );
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

		$sCronHook = $oFO->prefix( 'importexport_updatenotified' );
		if ( wp_next_scheduled( $sCronHook ) ) {
			wp_clear_scheduled_hook( $sCronHook );
		}

		if ( !wp_next_scheduled( $sCronHook ) ) {

			wp_schedule_single_event( $this->loadRequest()->ts() + 12, $sCronHook );

			preg_match( '#.*WordPress/.*\s+(.*)\s?#', $this->loadRequest()->server( 'HTTP_USER_AGENT' ), $aMatches );
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
				_wpsf__( 'Received notification that options import required.' )
				.' '.sprintf( _wpsf__( 'Current master site: %s' ), $oFO->getImportExportMasterImportUrl() ),
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
		$oReq = $this->loadRequest();

		$sSecretKey = $oReq->query( 'secret', '' );

		$sNetworkOpt = $oReq->query( 'network', '' );
		$bDoNetwork = !empty( $sNetworkOpt );
		$sUrl = $this->loadDP()->validateSimpleHttpUrl( $oReq->query( 'url', '' ) );

		if ( !$oFO->isImportExportSecretKey( $sSecretKey ) && !$this->isUrlOnWhitelist( $sUrl ) ) {
			return; // we show no signs of responding to invalid secret keys or unwhitelisted URLs
		}

		$bSuccess = false;
		$aData = array();

		if ( !$oFO->isPremium() ) {
			$nCode = 1;
			$sMessage = sprintf( _wpsf__( 'Not currently running %s Pro.' ), $this->getCon()->getHumanName() );
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
			$aData = $this->getExportData();
			$sMessage = 'Options Exported Successfully';

			$this->addToAuditEntry(
				sprintf( _wpsf__( 'Options exported to site %s.' ), $sUrl ), 1, 'options_exported'
			);

			if ( $bDoNetwork ) {
				if ( $sNetworkOpt === 'Y' ) {
					$oFO->addUrlToImportExportWhitelistUrls( $sUrl );
					$this->addToAuditEntry(
						sprintf( _wpsf__( 'Site added to export white list: %s.' ), $sUrl ),
						1,
						'export_whitelist_site_added'
					);
				}
				else {
					$oFO->removeUrlFromImportExportWhitelistUrls( $sUrl );
					$this->addToAuditEntry(
						sprintf( _wpsf__( 'Site removed from export white list: %s.' ), $sUrl ),
						1,
						'export_whitelist_site_removed'
					);
				}
			}
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
		$oFO = $this->getMod();
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

	/**
	 * @throws \Exception
	 */
	protected function setupCronImport() {
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getMod();
		$this->loadWpCronProcessor()
			 ->setNextRun( strtotime( 'tomorrow 1am' ) - get_option( 'gmt_offset' )*HOUR_IN_SECONDS + rand( 0, 1800 ) )
			 ->createCronJob( $this->getCronName(), array( $this, 'cron_autoImport' ) );
		// For auto update whitelist notifications:
		add_action( $oFO->prefix( 'importexport_updatenotified' ), array( $this, 'cron_autoImport' ) );
		add_action( $this->getMod()->prefix( 'deactivate_plugin' ), array( $this, 'deleteCron' ) );
	}

	/**
	 * @param string    $sMasterSiteUrl
	 * @param string    $sSecretKey
	 * @param bool|null $bEnableNetwork
	 * @param string    $sSiteResponse
	 * @return int
	 */
	public function runImport( $sMasterSiteUrl, $sSecretKey = '', $bEnableNetwork = null, &$sSiteResponse = '' ) {
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getMod();
		$oDP = $this->loadDP();

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
			$aEssential = array( 'scheme', 'host' );
			foreach ( $aEssential as $sKey ) {
				$bReady = $bReady && !empty( $aParts[ $sKey ] );
			}

			$sMasterSiteUrl = $oDP->validateSimpleHttpUrl( $sMasterSiteUrl ); // final clean

			if ( !$bReady || !$sMasterSiteUrl ) {
				$nErrorCode = 4;
			}
			else {
				$oFO->startImportExportHandshake();

				$aData = array(
					'shield_action' => 'importexport_export',
					'secret'        => $sSecretKey,
					'url'           => $this->loadWp()->getHomeUrl()
				);
				// Don't send the network setup request if it's the cron.
				if ( !is_null( $bEnableNetwork ) && !$this->loadWp()->isCron() ) {
					$aData[ 'network' ] = $bEnableNetwork ? 'Y' : 'N';
				}

				$sResponse = $this->loadFS()->getUrlContent( add_query_arg( $aData, $sMasterSiteUrl ) );
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
							sprintf( _wpsf__( 'Master Site URL set to %s.' ), $sMasterSiteUrl ),
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
				sprintf( _wpsf__( 'Options imported from %s.' ), $sImportSource ),
				1, 'options_imported'
			);
			$oFO->setImportExportLastImportHash( md5( serialize( $aImportData ) ) );
		}
		return $bImported;
	}

	public function cron_autoImport() {
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getMod();
		$this->runImport( $oFO->getImportExportMasterImportUrl() );
	}

	public function deleteCron() {
		$this->loadWpCronProcessor()->deleteCronJob( $this->getCronName() );
	}

	/**
	 * @return string
	 */
	protected function getCronName() {
		$oFO = $this->getMod();
		return $oFO->prefixOptionKey( $oFO->getDef( 'importexport_cron_name' ) );
	}
}